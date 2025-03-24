import('./quickSearch.js');

$('#searchForm').on('submit', function (event) {
    event.preventDefault();
    $('#interstitial').show();
    event.target.submit(); // Submit the form after showing the interstitial
});

$('#semanticSearchForm').on('submit', function (event) {
    event.preventDefault();
    $('#interstitial').show();
    event.target.submit(); // Submit the form after showing the interstitial
});

$('.interstitial').on('click', () =>
    $('#interstitial').show()
);

window.addEventListener('pageshow', (event) => {
    $('#interstitial').hide()
});

$("#greekTranslit").autocomplete({
    source: '/kereses/suggestGreek',
});