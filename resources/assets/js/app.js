import './quickSearch.js';

$('#semanticSearchForm').on('submit', function (event) {
    event.preventDefault();
    $('#interstitial').show();
    event.target.submit();
});

$('.interstitial').on('click', () =>
    $('#interstitial').show()
);


window.addEventListener('pageshow', (event) => {
    $('#interstitial').hide()
});