function filterGoals() {
    const filter = document.getElementById('filter-goals').value;
    const rows = document.querySelectorAll('table tbody tr');
    rows.forEach(row => {
        const priority = row.querySelector('.badge')?.textContent.trim().toLowerCase();
        if (filter === 'all' || priority === filter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function filterDebts() {
    const filter = document.getElementById('filter-debts').value;
    const rows = document.querySelectorAll('table tbody tr');
    rows.forEach(row => {
        const amount = parseFloat(row.querySelector('.expense')?.textContent.replace('$', ''));
        if (filter === 'all' || (filter === 'low' && amount < 1000) || (filter === 'high' && amount >= 1000)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function filterTransactions() {
    const filter = document.getElementById('filter-transactions').value;
    const rows = document.querySelectorAll('table tbody tr');
    rows.forEach(row => {
        const type = row.querySelector('.income, .expense')?.classList.contains(filter);
        if (filter === 'all' || type) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
