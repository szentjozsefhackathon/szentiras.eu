export function itemRender(ul, item) {
  if (item.cat === 'ref') {
    return $("<li>").append("<a><b>Igehely: </b>" + item.label + "</a>").appendTo(ul);
  } else {
    return $("<li>").append("<a>" + item.label + " <i>(" + item.linkLabel + ")</i></a>").appendTo(ul);
  }
}

function quickSearch() {

  $('#quickSearch').autocomplete({
    source: '/kereses/suggest',
    minLength: 2,
    messages: {
      noResults: '',
      results: () => { }
    },
    select: (event, ui) => {
      window.location = ui.item.link;
      return false;
    },
    search: (event, ui) => {
      $("#quickSearchHitsButtonContent").html('<span class="spinner-border-sm spinner-border"></span>');
    },
    response: (event, ui) => {
      if (ui.content[0]) {
        const hitCount = ui.content[0].hitCount;
        $("#quickSearchHitsButtonContent").html(hitCount + " találat");
      } else {
        $("#quickSearchHitsButtonContent").html("Nincs találat");
      }
    }

  }).data("ui-autocomplete")._renderItem = (ul, item) => {
    return itemRender(ul, item);
  };


  $('#quickSearch').on('input', (event) => {
    if (!event.target.value) {
      $("#quickSearchHitsButtonContent").html('<i class="bi bi-search"></i>');
    }
  });

  $('.quickSearchButton').on('click', () => {
    $('#interstitial').show();
    $('#quickSearchForm').trigger("submit");
  });

  $('#quickSearchHitsButton').on('click', () => {
    $('#interstitial').show();
    $('#quickSearchForm').trigger("submit");
  });

}

quickSearch();

$(".translationHit").on('click', function () {
  $('#interstitial').show();
  $(this).closest('form').trigger("submit");
});

$('.searchResultTranslationSelector').on('click', function () {
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
