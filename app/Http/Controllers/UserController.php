<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = $input['search'] ?? '';
        $users = User::with('role')->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))->orderBy('name')->paginate(15)->withQueryString();

        return view('users.index', compact('users', 'search'));
    }

    public function create()
    {
        return view('users.form', ['employee' => new User, 'roles' => Role::all()]);
    }

    public function store(Request $request)
    {
        $request->merge(['email' => strtolower(trim($request->input('email', '')))]);
        $input = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', Rule::exists('roles', 'id')],
            'password' => ['required', 'confirmed', Password::min(14)->letters()->numbers()],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($input) {
            $user = new User;
            $user->forceFill(collect($input)->except('reason')->all())->save();
            Audit::record('user.created', 'user', $user->id, after: $user->only(['name', 'email', 'role_id', 'is_active']), reason: $input['reason']);
        });

        return redirect()->route('users.index')->with('status', 'Employee account created. Share the temporary password through an approved secure channel.');
    }

    public function edit(User $user)
    {
        return view('users.form', ['employee' => $user, 'roles' => Role::all()]);
    }

    public function update(Request $request, User $user)
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'role_id' => ['required', Rule::exists('roles', 'id')],
            'is_active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($input, $user, $request) {
            // Serialize access administration, including simultaneous changes to administrators.
            Role::where('name', 'admin')->lockForUpdate()->firstOrFail();
            $target = User::lockForUpdate()->findOrFail($user->id);
            abort_if($target->id === $request->user()->id && (! $input['is_active'] || (int) $input['role_id'] !== $target->role_id), 422, 'You cannot deactivate yourself or change your own role.');
            $before = $target->only(['name', 'role_id', 'is_active']);
            $target->forceFill(collect($input)->except('reason')->all())->save();
            if (! $target->is_active || $before['role_id'] !== $target->role_id) {
                DB::table('sessions')->where('user_id', $target->id)->delete();
                $target->forceFill(['remember_token' => null])->save();
            }
            Audit::record('user.updated', 'user', $target->id, $before, $target->only(['name', 'role_id', 'is_active']), $input['reason']);
        });

        return redirect()->route('users.index')->with('status', 'Employee access updated.');
    }
}
