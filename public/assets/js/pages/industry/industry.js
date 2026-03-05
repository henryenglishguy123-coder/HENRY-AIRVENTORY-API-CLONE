
(function (window, $) {
    'use strict';

    window.initIndustry = function (config) {
        if (!config) {
            console.error('initIndustry: config is required');
            return;
        }
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': config.csrfToken
            }
        });
        var table = $('#industry-table').DataTable({
            processing: true,
            serverSide: false,
            pageLength: 100,
            ajax: {
                url: config.getIndustriesUrl,
                type: 'GET',
                dataSrc: function (json) {
                    return json.data || [];
                },
                error: function (xhr) {
                    console.error('Failed to fetch industries:', xhr);
                    toastr.error('Failed to load industries. Please try again later.');
                }
            },
            columns: [
                {
                    data: 'id', // Assuming ID is mapped
                    render: function (data) {
                        return `<input type="checkbox" class="row-checkbox form-check-input" value="${data}">`;
                    },
                    orderable: false,
                    searchable: false,
                },
                { data: 'id' },
                { data: 'meta.name', defaultContent: '-' },
                { data: 'categories_count', defaultContent: 0 },
                {
                    data: 'meta.status',
                    render: function (status) {
                        const statusClass = status ? 'bg-success' : 'bg-secondary';
                        const statusText = status ? 'Enable' : 'Disable';

                        return `<span class="badge rounded-pill ${statusClass}" disabled>${statusText}</span>`;
                    },
                    orderable: false, searchable: false
                },
                {
                    data: 'id',
                    render: function (id) {
                        return `<button class="btn btn-black btn-sm edit-btn" data-id="${id}"><i class="mdi mdi-eye"></i></button>`;
                    },
                    orderable: false, searchable: false
                },
            ],
            order: [[1, 'ASC']],
            drawCallback: function () {
                $('#mainCheckbox').prop('checked', false);
                $('.row-checkbox').prop('checked', false);
            }
        });


        function resetIndustryModal(title, action, data = {}) {
            const $modal = $('#industryModal');
            const $form = $('#industry-form');
            $('#industryModalLabel').text(title);
            $form[0].reset();
            $('.error-message').text('');
            $form.attr('data-action', action);
            $('#industry-id').val('');
            $modal.off('shown.bs.modal');
            $modal.modal('show');
            $modal.on('shown.bs.modal', function () {
                if (action === 'edit' && data.id) {
                    $('#industry-id').val(data.id);
                    $('#name').val(data.name ?? '');
                    const status = data.status ? '1' : '0';
                    $('#status option').prop('selected', false).filter(`[value="${status}"]`).prop('selected', true);
                }
                if (action === 'add') {
                    $('#industry-id').val('');
                }

            });
        }
        $('#add-industry-btn').on('click', function () {
            resetIndustryModal('Add Industry', 'add');
        });

        $(document).on('click', '.edit-btn', function () {
            const id = $(this).data('id');
            $.ajax({
                url: config.getIndustryUrl.replace(':id', id),
                type: 'GET',
                success: function (res) {
                    const industry = res?.data;
                    if (!industry) {
                        toastr.error('Industry data not found.');
                        return;
                    }
                    resetIndustryModal('Edit Industry', 'edit', {
                        id: industry.id,
                        name: industry.meta?.name ?? '',
                        status: industry.meta?.status
                    });
                },
                error: function () {
                    toastr.error('Failed to fetch industry details.');
                }
            });
        });

        $('#name, #status').on('focus', function () {
            $(this).siblings('.error-message').text('');
        });

        // Form validation (jQuery Validate assumed present)
        $('#industry-form').validate({
            rules: {
                name: { required: true, minlength: 2, maxlength: 50 },
                status: { required: true }
            },
            messages: {
                name: {
                    required: "Please enter an industry name.",
                    minlength: "Name must be at least 2 characters.",
                    maxlength: "Name must be less than 50 characters."
                },
                status: { required: "Please select a status." }
            },
            submitHandler: function (form) {
                const $submitBtn = $(form).find('button[type="submit"]');
                const action = $(form).attr('data-action');
                toggleButtonLoader(
                    $submitBtn,
                    true,
                    config.translations?.saving || 'Saving...'
                );
                $.ajax({
                    url: config.storeUrl,
                    type: 'POST',
                    data: $(form).serialize(),
                    success: function () {
                        toastr.success(
                            action === 'add'
                                ? (config.translations.addSuccess || 'Industry added successfully!')
                                : (config.translations.updateSuccess || 'Industry updated successfully!')
                        );
                        form.reset();
                        $('.error-message').text('');
                        $('#industryModal').modal('hide');
                        table.ajax.reload();
                    },
                    error: function (xhr) {
                        if (xhr.responseJSON?.errors) {
                            $.each(xhr.responseJSON.errors, function (key, value) {
                                $('#error-' + key).text(value[0]);
                            });
                        } else {
                            toastr.error(
                                config.translations.unexpectedError || 'An unexpected error occurred.'
                            );
                        }
                    },
                    complete: function () {
                        toggleButtonLoader($submitBtn, false);
                    }
                });
            }
        });

        $('#apply-bulk-action').on('click', function () {
            const selectedIds = $('.row-checkbox:checked')
                .map(function () {
                    return this.value;
                })
                .get();

            const action = $('#bulk-action').val();

            if (selectedIds.length === 0) {
                toastr.warning(
                    config.translations?.pleaseSelectRecord || 'Please select at least one record.'
                );
                return;
            }

            if (!action) {
                toastr.warning(
                    config.translations?.pleaseSelectAction || 'Please select an action.'
                );
                return;
            }

            Swal.fire({
                title: 'Are you sure?',
                text: 'This action will modify the selected industries.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Proceed',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (!result.isConfirmed) {
                    toastr.info('Action canceled.');
                    return;
                }
                const $bulkBtn = $('#apply-bulk-action');

                toggleButtonLoader($bulkBtn, true, 'Applying...');

                $.ajax({
                    url: config.bulkActionUrl,
                    type: 'POST',
                    data: {
                        ids: selectedIds,
                        action: action,
                        _token: config.csrfToken
                    },
                    success: function (response) {
                        toastr.success(response.message || 'Action completed successfully.');
                        table.ajax.reload(null, false);
                    },
                    error: function () {
                        toastr.error('Error processing request.');
                    },
                    complete: function () {
                        toggleButtonLoader($bulkBtn, false);
                    }
                });
            });
        });
        $('#mainCheckbox').on('click', function () {
            $('.row-checkbox').prop('checked', $(this).is(':checked'));
        });
    };
    function toggleButtonLoader($btn, isLoading, loadingText = 'Processing...') {
        if (isLoading) {
            if (!$btn.data('original-html')) {
                $btn.data('original-html', $btn.html());
            }
            $btn.prop('disabled', true).html(`<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${loadingText}`);
        } else {
            const originalHtml = $btn.data('original-html');
            if (originalHtml) {
                $btn.html(originalHtml);
            }
            $btn.prop('disabled', false);
        }
    }

})(window, jQuery);