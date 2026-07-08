/**
 * Admin JavaScript for Um dia No Parque plugin
 *
 * @package Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/admin
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('Um dia No Parque Admin initialized');

        // ============================================================
        // Media Uploader for single image fields
        // ============================================================
        var fileFrame;

        $('.umdnp-upload-image-btn').on('click', function(e) {
            e.preventDefault();

            var $btn    = $(this);
            var $field  = $btn.closest('.umdnp-image-field');
            var $input  = $field.find('.umdnp-image-id');
            var $preview = $field.find('.umdnp-image-preview');

            // Create the media frame if it doesn't exist.
            if (fileFrame) {
                fileFrame.open();
                return;
            }

            fileFrame = wp.media({
                title: 'Selecionar Imagem',
                button: {
                    text: 'Usar esta imagem'
                },
                multiple: false
            });

            fileFrame.on('select', function() {
                var attachment = fileFrame.state().get('selection').first().toJSON();
                $input.val(attachment.id);
                $preview.html('<img src="' + attachment.sizes.medium.url + '" style="max-width:300px; display:block; margin-bottom:8px;">');
                $btn.text('Trocar Imagem');

                // Show remove button if not present.
                if (!$field.find('.umdnp-remove-image-btn').length) {
                    $btn.after(' <button type="button" class="button umdnp-remove-image-btn" style="margin-left:4px;">Remover</button>');
                }
            });

            fileFrame.open();
        });

        // Remove single image.
        $(document).on('click', '.umdnp-remove-image-btn', function(e) {
            e.preventDefault();
            var $btn     = $(this);
            var $field   = $btn.closest('.umdnp-image-field');
            var $input   = $field.find('.umdnp-image-id');
            var $preview = $field.find('.umdnp-image-preview');
            var $upload  = $field.find('.umdnp-upload-image-btn');

            $input.val('');
            $preview.html('');
            $upload.text('Selecionar Imagem');
            $btn.remove();
        });

        // ============================================================
        // Media Uploader for gallery (multiple images) fields
        // ============================================================
        var galleryFrame;

        $('.umdnp-upload-gallery-btn').on('click', function(e) {
            e.preventDefault();

            var $btn     = $(this);
            var $field   = $btn.closest('.umdnp-gallery-field');
            var $input   = $field.find('.umdnp-gallery-ids');
            var $preview = $field.find('.umdnp-gallery-preview');

            // Preselect already chosen images.
            var selectedIds = $input.val();
            var selection   = [];
            if (selectedIds) {
                $.each(selectedIds.split(','), function(i, id) {
                    id = parseInt(id.trim(), 10);
                    if (id) {
                        selection.push(id);
                    }
                });
            }

            if (galleryFrame) {
                galleryFrame.open();
                return;
            }

            galleryFrame = wp.media({
                title: 'Gerenciar Galeria',
                button: {
                    text: 'Atualizar Galeria'
                },
                multiple: true,
                library: {
                    type: 'image'
                }
            });

            // Set initial selection on open.
            galleryFrame.on('open', function() {
                var $library = galleryFrame.state().get('library');
                if (selection.length) {
                    var models = [];
                    $.each(selection, function(i, id) {
                        var attachment = wp.media.attachment(id);
                        attachment.fetch();
                        models.push(attachment);
                    });
                    $library.reset(models);
                    galleryFrame.state().get('selection').reset(models);
                }
            });

            galleryFrame.on('select', function() {
                var attachments = galleryFrame.state().get('selection').toJSON();
                var ids = [];
                var html = '';

                $.each(attachments, function(i, att) {
                    ids.push(att.id);
                    var thumb = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
                    html += '<div class="umdnp-gallery-item" data-id="' + att.id + '" style="position:relative;width:80px;height:80px;border:1px solid #ddd;border-radius:4px;overflow:hidden;display:inline-block;">';
                    html += '<img src="' + thumb + '" style="width:100%;height:100%;object-fit:cover;">';
                    html += '<button type="button" class="umdnp-gallery-remove" style="position:absolute;top:2px;right:2px;background:rgba(0,0,0,0.6);color:#fff;border:none;border-radius:50%;width:18px;height:18px;font-size:12px;line-height:18px;text-align:center;cursor:pointer;padding:0;">&times;</button>';
                    html += '</div>';
                });

                $input.val(ids.join(','));
                $preview.html(html);

                // Add clear button if not present.
                if (ids.length && !$field.find('.umdnp-gallery-clear-btn').length) {
                    $btn.after(' <button type="button" class="button umdnp-gallery-clear-btn" style="margin-left:4px;">Limpar Galeria</button>');
                }
            });

            galleryFrame.open();
        });

        // Remove individual gallery item.
        $(document).on('click', '.umdnp-gallery-remove', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $item   = $(this).closest('.umdnp-gallery-item');
            var $field  = $item.closest('.umdnp-gallery-field');
            var $input  = $field.find('.umdnp-gallery-ids');
            var removedId = parseInt($item.data('id'), 10);
            var ids = [];

            $.each($input.val().split(','), function(i, id) {
                id = parseInt(id.trim(), 10);
                if (id && id !== removedId) {
                    ids.push(id);
                }
            });

            $item.remove();
            $input.val(ids.join(','));

            // Hide clear button if gallery is empty.
            if (!ids.length) {
                $field.find('.umdnp-gallery-clear-btn').remove();
            }
        });

        // Clear entire gallery.
        $(document).on('click', '.umdnp-gallery-clear-btn', function(e) {
            e.preventDefault();
            var $field  = $(this).closest('.umdnp-gallery-field');
            var $input  = $field.find('.umdnp-gallery-ids');
            var $preview = $field.find('.umdnp-gallery-preview');

            $input.val('');
            $preview.html('');
            $(this).remove();
        });
    });

    // ============================================================
    // IMPORT — XLSX upload + batch processing
    // ============================================================

    var importState = {
        running: false,
        step: 0,
        offset: 0,
        totalUcs: 0
    };

    function importLog(msg, type) {
        var $msg = $('#umdnp-import-message');
        $msg.show().removeClass('notice-success notice-error notice-info').addClass('notice-' + (type || 'info'));
        $msg.find('p').remove().end().append('<p>' + msg + '</p>');
    }

    function importSetStatus(text) {
        $('#umdnp-import-status').text(text);
    }

    function importSetProgress(pct) {
        $('#umdnp-import-progress-fill').css('width', Math.min(pct, 100) + '%');
    }

    function importShowResults(ucs, atvs, errs) {
        $('#umdnp-import-result-ucs').text(ucs);
        $('#umdnp-import-result-atividades').text(atvs);
        $('#umdnp-import-result-errors').text(errs);
        $('#umdnp-import-results').show();
        $('#umdnp-import-actions').hide();
    }

    function importStartProcessing() {
        importState.running = true;
        importState.step = 0;
        importState.offset = 0;

        $('#umdnp-import-upload-section').hide();
        $('#umdnp-import-progress-section').show();
        $('#umdnp-import-start-btn').hide();
        $('#umdnp-import-cancel-btn').show();
        importSetProgress(0);

        importProcessStep();
    }

    function importProcessStep() {
        if (!importState.running) return;

        importSetStatus(umdnp_import.i18n.processing);

        $.post(umdnp_import.ajax_url, {
            action: 'umdnp_import_process',
            nonce:  umdnp_import.nonce,
            step:   importState.step,
            offset: importState.offset
        }, function(resp) {
            if (!resp.success) {
                importState.running = false;
                importLog(resp.data.message || umdnp_import.i18n.error, 'error');
                $('#umdnp-import-cancel-btn').hide();
                $('#umdnp-import-start-btn').show().text('Tentar Novamente');
                return;
            }

            var d = resp.data;

            if (d.done) {
                importState.running = false;
                importSetProgress(100);
                importSetStatus(umdnp_import.i18n.done);
                importLog(d.message, 'success');
                importShowResults(d.imported_uc || 0, d.imported_atv || 0, d.total_errors || 0);
                $('#umdnp-import-cancel-btn').hide();
                // Clean up file and transient after completion
                $.post(umdnp_import.ajax_url, {
                    action: 'umdnp_import_cleanup',
                    nonce:  umdnp_import.nonce
                });
                return;
            }

            // Update progress
            importState.step   = d.step;
            importState.offset = d.offset;

            if (d.total > 0) {
                var pct = Math.round((d.offset / d.total) * 100);
                importSetProgress(pct);
            }

            importSetStatus(d.message || umdnp_import.i18n.processing);

            // Next batch
            importProcessStep();
        }).fail(function() {
            importState.running = false;
            importLog(umdnp_import.i18n.error, 'error');
            $('#umdnp-import-cancel-btn').hide();
            $('#umdnp-import-start-btn').show().text('Tentar Novamente');
        });
    }

    // Upload form submit
    $(document).on('submit', '#umdnp-import-form', function(e) {
        e.preventDefault();

        var file = $('#umdnp-import-file')[0].files[0];
        if (!file) {
            importLog('Selecione um arquivo.', 'error');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'umdnp_import_upload');
        formData.append('nonce',  umdnp_import.nonce);
        formData.append('import_file', file);

        $('#umdnp-import-upload-btn').prop('disabled', true).text(umdnp_import.i18n.uploading + '...');
        importLog('', 'info');

        $.ajax({
            url: umdnp_import.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(resp) {
                $('#umdnp-import-upload-btn').prop('disabled', false).text('Enviar e Importar');

                // Handle malformed response (e.g. PHP warnings before JSON)
                if (typeof resp === 'string') {
                    importLog(umdnp_import.i18n.error, 'error');
                    return;
                }

                if (resp.success) {
                    importLog(resp.data.message, 'success');
                    importState.totalUcs = resp.data.total_rows || 0;
                    importStartProcessing();
                } else {
                    importLog(resp.data.message || umdnp_import.i18n.error, 'error');
                }
            },
            error: function() {
                $('#umdnp-import-upload-btn').prop('disabled', false).text('Enviar e Importar');
                importLog(umdnp_import.i18n.error, 'error');
            }
        });
    });

    // Start/continue button
    $(document).on('click', '#umdnp-import-start-btn', function() {
        importStartProcessing();
    });

    // Cancel button
    $(document).on('click', '#umdnp-import-cancel-btn', function() {
        if (!confirm(umdnp_import.i18n.confirm_cancel)) return;
        importState.running = false;

        // Clean up file and transient.
        $.post(umdnp_import.ajax_url, {
            action: 'umdnp_import_cleanup',
            nonce:  umdnp_import.nonce
        });

        importResetUI();
    });

    // Reset / new import
    $(document).on('click', '#umdnp-import-reset-btn', function() {
        $.post(umdnp_import.ajax_url, {
            action: 'umdnp_import_cleanup',
            nonce:  umdnp_import.nonce
        }, function() {
            importResetUI();
        });
    });

    function importResetUI() {
        $('#umdnp-import-results').hide();
        $('#umdnp-import-progress-section').hide();
        $('#umdnp-import-upload-section').show();
        $('#umdnp-import-start-btn').hide();
        $('#umdnp-import-cancel-btn').hide();
        importSetProgress(0);
        importSetStatus('Aguardando...');
        $('#umdnp-import-message').hide().find('p').remove();
    }

})(jQuery);
