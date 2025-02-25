window.initPdfModalScripts = function () {

    const options = () => {
        return $.param({
            'headings': $('#pdfHeadings').prop('checked'),
            'nums': $('#pdfNums').prop('checked'),
            'refs': $('#pdfRefs').prop('checked'),
            'quantity': $('#pdfQuantity').val()
        });
    };

    $('#pdfModal').on('shown.bs.modal', (event) => {
        const ref = $('#previewContainer').data('ref');
        const translationId = $('#previewContainer').data('translation');
        $("#pdfDownload").off('click');        
        $("#pdfDownload").on('click', (event) => {
            window.open(`/pdf/ref/${translationId}/${ref}?${options()}`);
            $('#pdfDownload').blur();
            bootstrap.Modal.getInstance($('#pdfModal')).hide();
        });
    });
};