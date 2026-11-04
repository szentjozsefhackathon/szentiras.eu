/**
 * Debounce utility function to delay function execution
 * @param {Function} func - Function to debounce
 * @param {number} delay - Delay in milliseconds
 * @returns {Function} Debounced function
 */
function debounce(func, delay) {
  let timeoutId;
  return function (...args) {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => func.apply(this, args), delay);
  };
}

export function itemRender(ul, item) {
  if (item.cat === 'ref') {
    return $("<li>").append("<a><b>Igehely: </b>" + item.label + "</a>").appendTo(ul);
  } else {
    return $("<li>").append("<a>" + item.label + " <i>(" + item.linkLabel + ")</i></a>").appendTo(ul);
  }
}

function quickSearch() {
  const $quickSearch = $('#quickSearch');
  let currentRequest = null;

  // Create debounced search function
  const debouncedSearch = debounce(() => {
    $quickSearch.autocomplete('search');
  }, 300);

  $quickSearch.autocomplete({
    source: (request, response) => {
      // Cancel previous request if still pending
      if (currentRequest) {
        currentRequest.abort();
      }

      currentRequest = $.ajax({
        url: '/kereses/suggest',
        type: 'GET',
        data: { term: request.term },
        dataType: 'json',
        success: (data) => {
          currentRequest = null;
          response(data);
        },
        error: () => {
          currentRequest = null;
          response([]);
        }
      });
    },
    minLength: 2,
    autoFocus: false,
    position: {
      my: "left top",
      at: "left bottom",
      collision: "none"
    },
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
      // Find the badge and count span within the same form as the input
      const input = $(event.target);
      const form = input.closest('form');
      const badge = form.find("#quickSearchResultsBadge");
      const countSpan = form.find("#quickSearchResultsCount");
      const buttonContent = form.find("#quickSearchHitsButtonContent");
      
      if (ui.content[0]) {
        const hitCount = ui.content[0].hitCount;
        countSpan.text(hitCount);
        badge.removeClass("d-none");
        // Keep button content as just the search icon
        buttonContent.html('<i class="bi-search"></i>');
      } else {
        badge.addClass("d-none");
        buttonContent.html('<i class="bi-search"></i>');
      }
    }

  }).data("ui-autocomplete")._renderItem = (ul, item) => {
    return itemRender(ul, item);
  };

  // Debounced input handler
  $quickSearch.on('input', debounce((event) => {
    if (!event.target.value) {
      const input = $(event.target);
      const form = input.closest('form');
      form.find("#quickSearchResultsBadge").addClass("d-none");
      form.find("#quickSearchHitsButtonContent").html('<i class="bi-search"></i>');
    } else {
      // Trigger debounced search on input
      debouncedSearch();
    }
  }, 300));

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

$(document).on('click', '.searchResultTranslationSelector', function () {
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
