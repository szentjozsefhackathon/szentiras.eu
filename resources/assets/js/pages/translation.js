import quickChapterSelector from '../quickChapterSelector.js';

const translation  = $('#data').data('translation');
quickChapterSelector(translation);
$("#showToc").click(function() {
    $(".interstitial").show();
    window.location=$(this).data('url');
});
$("#hideToc").click(function() {
    $(".interstitial").show();
    window.location=$(this).data('url');
});