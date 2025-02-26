import './pages/home.js';
import './pdfDialog.js';
import('./quickSearch.js');
import('./textDisplay.js');

$('#searchForm').on('submit', function(event) {
    event.preventDefault(); 
    $('.interstitial').show();
    event.target.submit(); // Submit the form after showing the interstitial
});

$('#semanticSearchForm').on('submit', function(event) {
    event.preventDefault(); 
    $('.interstitial').show();
    event.target.submit(); // Submit the form after showing the interstitial
});
