(function () {
    const page = $('.registration-search-page');
    if (!page.length) return;

    const escapeHtml = (value) => $('<div>').text(value ?? '—').html();
    const imageUrl = (image) => image
        ? `${page.data('storage-url')}/${String(image).replace(/^\/+/, '')}`
        : page.data('fallback-image');

    $(document).off('submit.registrationSearch', '#registrationSearchForm')
        .on('submit.registrationSearch', '#registrationSearchForm', function (event) {
            event.preventDefault();

            const form = $(this);
            const button = form.find('button[type="submit"]');
            const input = $('#registrationNumbers').val();
            $('#registrationSearchError').text('');
            button.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Searching...');

            $.ajax({
                url: page.data('results-url'),
                method: 'POST',
                data: { reg_nos: input },
                success: function (response) {
                    const body = $('#registrationResultsBody').empty();
                    $('#registrationResultCount').text(response.count);
                    $('#registrationResultsCard').prop('hidden', false);
                    $('#openRegistrationDownload').prop('disabled', response.count === 0);

                    if (!response.participants.length) {
                        body.append('<tr class="registration-empty-row"><td colspan="9">No participants found for the supplied registration numbers.</td></tr>');
                    }

                    response.participants.forEach(function (participant, index) {
                        body.append(`
                            <tr>
                                <td>${index + 1}</td>
                                <td class="registration-result-image-cell">
                                    <strong>${escapeHtml(participant.sl)}</strong>
                                    <img src="${escapeHtml(imageUrl(participant.image))}" alt="${escapeHtml(participant.name)}" onerror="this.onerror=null;this.src='${escapeHtml(page.data('fallback-image'))}'">
                                </td>
                                <td>${escapeHtml(participant.age)}</td>
                                <td>${escapeHtml(participant.occupation)}</td>
                                <td>${escapeHtml(participant.status)}</td>
                                <td>${escapeHtml(participant.name)}</td>
                                <td>${escapeHtml(participant.branch)}</td>
                                <td>${escapeHtml(participant.reg_no)}</td>
                                <td>${escapeHtml(participant.mobile)}</td>
                            </tr>`);
                    });

                    const missing = $('#registrationMissingNotice');
                    if (response.missing.length) {
                        missing.text(`Not found (${response.missing.length}): ${response.missing.join(', ')}`).prop('hidden', false);
                    } else {
                        missing.text('').prop('hidden', true);
                    }

                    $('.registration-export-form input[name="reg_nos"]').val(input);
                    $('.registration-export-form').eq(0).attr('action', page.data('pdf-url'));
                    $('.registration-export-form').eq(1).attr('action', page.data('xlsx-url'));
                },
                error: function (xhr) {
                    const message = xhr.responseJSON?.errors?.reg_nos?.[0]
                        || xhr.responseJSON?.message
                        || 'Unable to search. Please try again.';
                    $('#registrationSearchError').text(message);
                },
                complete: function () {
                    button.prop('disabled', false).html('<i class="fa-solid fa-magnifying-glass"></i> Proceed');
                }
            });
        });

    $(document).off('click.registrationDownload', '#openRegistrationDownload')
        .on('click.registrationDownload', '#openRegistrationDownload', function () {
            $('#registrationDownloadModal').addClass('show').attr('aria-hidden', 'false');
        });

    $(document).off('click.registrationDownloadClose', '.registration-download-close')
        .on('click.registrationDownloadClose', '.registration-download-close', closeModal);

    $(document).off('click.registrationDownloadBackdrop', '#registrationDownloadModal')
        .on('click.registrationDownloadBackdrop', '#registrationDownloadModal', function (event) {
            if (event.target === this) closeModal();
        });

    $(document).off('keydown.registrationDownload').on('keydown.registrationDownload', function (event) {
        if (event.key === 'Escape') closeModal();
    });

    function closeModal() {
        $('#registrationDownloadModal').removeClass('show').attr('aria-hidden', 'true');
    }
})();
