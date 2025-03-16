$('#quickSearch').autocomplete({
    source: '/kereses/suggest',
    minLength: 2,
    messages: {
      noResults: '',
      results: () => {}
    },
    select: (event, ui) => {
      window.location = ui.item.link;
      return false;
    }
  }).data("ui-autocomplete")._renderItem = (ul, item) => {
    if (item.cat === 'ref') {
      return $("<li>").append("<a><b>Igehely: </b>" + item.label + "</a>").appendTo(ul);
    } else {
      return $("<li>").append("<a>" + item.label + " <i>(" + item.linkLabel + ")</i></a>").appendTo(ul);
    }
  };
  
  $('.quickSearchButton').on('click', () => {
    $('#interstitial').show();
    $('#quickSearchForm').trigger("submit");
  });

  $(".translationHit").on('click', function() {    
    $('#interstitial').show();
    $(this).closest('form').trigger("submit");
  });

  $('.searchResultTranslationSelector').on('click', function() {
    $(this).siblings().removeClass('active');
    $(this).addClass('active');
    const idToShow = $(this).data('target');   
    const divToShow = $(idToShow);
    $(divToShow).siblings().hide();
    divToShow.show();
  });

var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl)
})
