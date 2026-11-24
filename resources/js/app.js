const toggle = document.querySelector('.menu-toggle');
const sidebar = document.querySelector('#sidebar');
toggle?.addEventListener('click', () => {
    const open = sidebar.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(open));
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && sidebar?.classList.contains('is-open')) {
        sidebar.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
    }
});
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', event => {
        const confirmation = event.submitter?.dataset.confirm || form.dataset.confirm;
        if (confirmation && !window.confirm(confirmation)) {
            event.preventDefault();
            return;
        }
        const button = event.submitter;
        if (button) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.textContent = form.method.toLowerCase() === 'get' ? 'Loading…' : 'Please wait…';
        }
    });
});
window.addEventListener('pageshow', () => document.querySelectorAll('[data-original-text]').forEach(button => {
    button.textContent = button.dataset.originalText;
    button.disabled = false;
}));

document.querySelectorAll('[data-add-token]').forEach(button => {
    button.addEventListener('click', () => {
        const target = document.getElementById(button.dataset.addToken);
        const source = document.getElementById(button.dataset.tokenSource);
        const tokens = target.value.split('|').map(value => value.trim()).filter(Boolean);
        if (source.value.trim() && !tokens.includes(source.value.trim())) tokens.push(source.value.trim());
        target.value = tokens.join(' | ');
        target.focus();
    });
});

document.querySelector('[data-filter-state]')?.addEventListener('change', event => {
    document.querySelector('[data-filter-county]').value = '';
    event.target.form.requestSubmit();
});

const contacts = document.getElementById('additional-contacts');
let contactIndex = Math.max(-1, ...Array.from(contacts?.querySelectorAll('input[name]') || []).map(input => Number(input.name.match(/contacts\[(\d+)\]/)?.[1] ?? -1))) + 1;
document.getElementById('add-contact')?.addEventListener('click', () => {
    if (contacts.children.length >= 8) return;
    const row = document.createElement('div');
    row.className = 'contact-row row g-2 mb-3';
    row.dataset.contactRow = '';
    for (const field of ['name', 'email', 'phone']) {
        const column = document.createElement('div');
        column.className = 'col-md-3';
        const label = document.createElement('label');
        label.className = 'form-label';
        label.htmlFor = `contact-${contactIndex}-${field}`;
        label.textContent = `Additional ${field}`;
        const input = document.createElement('input');
        input.className = 'form-control';
        input.id = label.htmlFor;
        input.name = `contacts[${contactIndex}][${field}]`;
        input.type = field === 'email' ? 'email' : 'text';
        input.required = field === 'name';
        input.maxLength = field === 'email' ? 254 : field === 'phone' ? 40 : 120;
        column.append(label, input);
        row.append(column);
    }
    const column = document.createElement('div');
    column.className = 'col-md-3 d-flex align-items-end';
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'btn btn-light';
    remove.dataset.removeContact = '';
    remove.textContent = 'Remove contact';
    column.append(remove);
    row.append(column);
    contacts.append(row);
    contactIndex++;
    row.querySelector('input').focus();
});
contacts?.addEventListener('click', event => {
    if (event.target.matches('[data-remove-contact]')) event.target.closest('[data-contact-row]').remove();
});
