/* CreatorNetworkLeads – App JS */
$(function () {

    /* ── Flash message auto-dismiss ───────────────────────── */
    setTimeout(function () {
        $('.alert-dismissible').fadeOut(600, function () { $(this).remove(); });
    }, 5000);

    /* ── Confirm delete modals ─────────────────────────────── */
    $(document).on('click', '.btn-confirm-delete', function (e) {
        e.preventDefault();
        var href   = $(this).attr('href') || $(this).data('href');
        var label  = $(this).data('label') || 'this item';
        if (confirm('Are you sure you want to delete ' + label + '? This cannot be undone.')) {
            window.location.href = href;
        }
    });

    /* ── Admin sidebar mobile toggle ────────────────────────── */
    $('#sidebarToggle').on('click', function () {
        $('#adminSidebar').toggleClass('open');
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#adminSidebar, #sidebarToggle').length) {
            $('#adminSidebar').removeClass('open');
        }
    });

    /* ── Lead card "View" expand (dashboard) ────────────────── */
    $(document).on('click', '.lead-card-toggle', function () {
        $(this).closest('.lead-card').find('.lead-card-body').toggleClass('d-none');
    });

    /* ── Select all checkboxes (admin tables) ───────────────── */
    $('#checkAll').on('change', function () {
        $('input[name="selected[]"]').prop('checked', this.checked);
        updateBulkBar();
    });
    $(document).on('change', 'input[name="selected[]"]', function () {
        var total   = $('input[name="selected[]"]').length;
        var checked = $('input[name="selected[]"]:checked').length;
        $('#checkAll').prop('indeterminate', checked > 0 && checked < total);
        $('#checkAll').prop('checked', checked === total);
        updateBulkBar();
    });
    function updateBulkBar() {
        var count = $('input[name="selected[]"]:checked').length;
        if (count > 0) {
            $('#bulkBar').removeClass('d-none');
            $('#bulkCount').text(count);
        } else {
            $('#bulkBar').addClass('d-none');
        }
    }

    /* ── Filter form auto-submit on select change ───────────── */
    $('.auto-submit').on('change', function () {
        $(this).closest('form').submit();
    });

    /* ── DataTable-style client sort (lightweight) ──────────── */
    $(document).on('click', 'th[data-sort]', function () {
        var col   = $(this).data('sort');
        var table = $(this).closest('table');
        var tbody = table.find('tbody');
        var rows  = tbody.find('tr').toArray();
        var dir   = $(this).hasClass('asc') ? -1 : 1;
        $('th[data-sort]').removeClass('asc desc');
        $(this).addClass(dir === 1 ? 'asc' : 'desc');
        rows.sort(function (a, b) {
            var av = $(a).find('td').eq(col).text().trim().toLowerCase();
            var bv = $(b).find('td').eq(col).text().trim().toLowerCase();
            return av < bv ? -dir : av > bv ? dir : 0;
        });
        $.each(rows, function (i, row) { tbody.append(row); });
    });

    /* ── Dark mode toggle ───────────────────────────────────── */
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('cnl-theme', theme);
        var isDark = theme === 'dark';
        $('#themeToggle i').attr('class', isDark ? 'bi bi-sun-fill' : 'bi bi-moon-fill');
        $('#themeToggle').attr('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    }

    // Sync icon to current theme (theme already applied by inline head script)
    (function syncIcon() {
        var t = document.documentElement.getAttribute('data-bs-theme') || 'light';
        var isDark = t === 'dark';
        $('#themeToggle i').attr('class', isDark ? 'bi bi-sun-fill' : 'bi bi-moon-fill');
        $('#themeToggle').attr('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    })();

    $('#themeToggle').on('click', function () {
        var current = document.documentElement.getAttribute('data-bs-theme') || 'light';
        applyTheme(current === 'dark' ? 'light' : 'dark');
    });

    /* ── Message template copy buttons ─────────────────────── */
    $(document).on('click', '.btn-copy-template', function () {
        var targetId = $(this).data('copy-target');
        var source = document.getElementById(targetId);
        if (!source) {
            return;
        }

        var text = source.value || source.textContent || '';
        if (!text) {
            return;
        }

        var btn = $(this);
        var originalHtml = btn.html();

        function markCopied() {
            btn.html('<i class="bi bi-check2 me-1"></i>Copied');
            setTimeout(function () {
                btn.html(originalHtml);
            }, 1400);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(markCopied).catch(function () {
                source.focus();
                source.select();
                if (document.execCommand('copy')) {
                    markCopied();
                }
            });
            return;
        }

        source.focus();
        source.select();
        if (document.execCommand('copy')) {
            markCopied();
        }
    });

});
