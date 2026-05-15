

<script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle("active");
    }

    function toggleDescription(id) {
        const description = document.getElementById('description-' + id);
        if (description.style.display === 'none') {
            description.style.display = 'block';
        } else {
            description.style.display = 'none';
        }
    }

    function toggleActions(id) {
        const actions = document.getElementById('actions-' + id);
        if (actions.style.display === 'none') {
            actions.style.display = 'block';
        } else {
            actions.style.display = 'none';
        }
    }
    function toggleDisciplines(id) {
        const disciplines = document.getElementById('disciplines-' + id);
        if (disciplines.style.display === 'none') {
            disciplines.style.display = 'block';
        } else {
            disciplines.style.display = 'none';
        }
    }

    function selectTab(group, index) {
        document.querySelectorAll('[data-tab-group="' + group + '"]').forEach(function (el) {
            const matches = el.dataset.tabIndex === String(index);
            if (el.classList.contains('tab-content')) {
                el.style.display = matches ? 'block' : 'none';
            } else if (el.tagName === 'LI') {
                if (matches) el.classList.add('is-active');
                else el.classList.remove('is-active');
            }
        });
    }

</script>