/* Shipment Tracker — Admin JS */
'use strict';

$(function () {

    // ── Load projects & invoices when client changes ──
    $(document).on('changed.bs.select change', '#client_id', function () {
        var clientId = $(this).val();
        if (!clientId) {
            $('#project_id').find('option:not(:first)').remove();
            $('#invoice_id').find('option:not(:first)').remove();
            if ($('#project_id').hasClass('selectpicker')) $('#project_id').selectpicker('refresh');
            if ($('#invoice_id').hasClass('selectpicker')) $('#invoice_id').selectpicker('refresh');
            return;
        }

        $.ajax({
            url:  admin_url + 'shipment_tracker/shipments_admin/ajax_client_data',
            type: 'POST',
            data: { client_id: clientId },
            dataType: 'json',
            success: function (r) {
                if (!r.success) { return; }

                var $p = $('#project_id');
                $p.find('option:not(:first)').remove();
                $.each(r.projects, function (i, p) {
                    $p.append($('<option>').val(p.id).text(p.name));
                });
                if ($p.hasClass('selectpicker')) { $p.selectpicker('refresh'); }

                var $inv = $('#invoice_id');
                $inv.find('option:not(:first)').remove();
                $.each(r.invoices, function (i, inv) {
                    $inv.append($('<option>').val(inv.id).text('#' + (inv.number || inv.id)));
                });
                if ($inv.hasClass('selectpicker')) { $inv.selectpicker('refresh'); }
            }
        });
    });

    // ── Quick status update on view page ──────────────
    $(document).on('click', '#st-update-btn', function () {
        var $btn   = $(this);
        var id     = $btn.data('id');
        var status = $('#st-status').val();
        var note   = $('#st-note').val();
        var notify = $('#st-notify').is(':checked') ? 1 : 0;

        if (!status) { return; }

        $btn.prop('disabled', true)
            .html('<i class="fa fa-spinner fa-spin"></i> Updating...');

        $.ajax({
            url:      admin_url + 'shipment_tracker/shipments_admin/ajax_status',
            type:     'POST',
            data:     { id: id, status: status, note: note, notify: notify },
            dataType: 'json',
            success: function (r) {
                if (r.success) {
                    $('#st-result').html(
                        '<div class="alert alert-success"><i class="fa fa-check"></i> ' + r.message + '</div>'
                    );
                    // Reload page after short delay to show updated history
                    setTimeout(function () { location.reload(); }, 1200);
                } else {
                    $('#st-result').html(
                        '<div class="alert alert-danger"><i class="fa fa-times"></i> ' + r.message + '</div>'
                    );
                    $btn.prop('disabled', false)
                        .html('<i class="fa fa-check"></i> Update Status');
                }
            },
            error: function () {
                $('#st-result').html('<div class="alert alert-danger">Request failed.</div>');
                $btn.prop('disabled', false)
                    .html('<i class="fa fa-check"></i> Update Status');
            }
        });
    });

    // ── Delete confirmation ───────────────────────────
    $(document).on('click', 'a._delete[data-table="shipments"]', function (e) {
        e.preventDefault();
        var href = $(this).attr('href');
        if (confirm('Are you sure you want to delete this shipment? This cannot be undone.')) {
            window.location.href = href;
        }
    });

});