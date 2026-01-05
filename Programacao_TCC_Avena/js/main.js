document.addEventListener('DOMContentLoaded', function() {
    const table = document.querySelector('.container-data table');
    if (table) {
        const rows = table.querySelectorAll('tr');

        rows.forEach((row, index) => {
            if (index > 0) { // Pula o cabeçalho
                row.addEventListener('click', () => {
                    const cells = row.querySelectorAll('td');
                    const id = cells[0].innerText;
                    const nome = cells[1].innerText;
                    const email = cells[2].innerText;
                    const telefone = cells[3].innerText;

                    document.querySelector('input[name="id"]').value = id;
                    document.querySelector('input[name="nome"]').value = nome;
                    document.querySelector('input[name="email"]').value = email;
                    document.querySelector('input[name="telefone"]').value = telefone;
                });
            }
        });
    }
});
