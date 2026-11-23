<script>
    (() => {
        const labelCells = (table) => {
            const headerRow = table.querySelector(':scope > thead > tr:last-child');
            const records = table.querySelector(':scope > .fi-ta-records');

            if (! headerRow || ! records) {
                return;
            }

            const labels = [...headerRow.children].map((cell) => cell.textContent.trim());

            records.querySelectorAll(':scope > tr').forEach((row) => {
                const cells = [...row.children];

                if (cells.length !== labels.length) {
                    return;
                }

                cells.forEach((cell, index) => {
                    if (labels[index] && cell.dataset.label !== labels[index]) {
                        cell.dataset.label = labels[index];
                    }

                    const isEmpty = cell.textContent.trim() === '' && ! cell.querySelector('img, svg, input, button, a');

                    cell.toggleAttribute('data-empty', isEmpty && ! cell.classList.contains('fi-ta-actions-cell'));
                });
            });
        };

        let pending = false;

        const run = () => {
            pending = false;
            document.querySelectorAll('.fi-ta-table').forEach(labelCells);
        };

        const schedule = () => {
            if (! pending) {
                pending = true;
                requestAnimationFrame(run);
            }
        };

        new MutationObserver(schedule).observe(document.documentElement, { childList: true, subtree: true });
        document.addEventListener('DOMContentLoaded', schedule);
    })();
</script>
