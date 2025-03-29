import { itemRender } from '../quickSearch.js';

$('#textSearchForm').on('submit', function (event) {
    event.preventDefault();
    $('#interstitial').show();
    event.target.submit();
});

$('#searchButton').on('click', function (event) {
    event.preventDefault();
    $('#textSearchForm').submit();
});


$("#greekTranslit").autocomplete({
    source: '/kereses/suggestStrong',
});

$('#greekTextSearchButton').on('click', function (event) {
    event.preventDefault();
    $('#greekTextSearchForm').submit();
});

$("#greekText").autocomplete({
    source: function (request, response) {
        $.ajax({
            url: "/kereses/suggestGreek",
            dataType: "json",
            data: {
                term: request.term,
                book: $('#greek-search-book').val()
            },
            success: function (data) {
                response(data);
            }
        });
    },
    minLength: 2,
    search: (event, ui) => {
        $("#greekTextSearchHitsButtonContent").html('<span class="spinner-border-sm spinner-border"></span> Keresés');
    },
    response: (event, ui) => {
        if (ui.content[0]) {
            const hitCount = ui.content[0].hitCount;
            $("#greekTextSearchHitsButtonContent").html(`${hitCount} találat <i class="bi bi-caret-right"></i>`);
        } else {
            $("#greekTextSearchHitsButtonContent").html("Nincs találat");
        }
    },
    select: (event, ui) => {
        window.location = ui.item.link;
        return false;
    }
}).data("ui-autocomplete")._renderItem = (ul, item) => {
    return itemRender(ul, item);
};




$('#searchInput').autocomplete({
    source: function (request, response) {
        $.ajax({
            url: "/kereses/suggest",
            dataType: "json",
            data: {
                term: request.term,
                book: $('#text-search-book').val(),
                translation: $('#text-search-translation').val(),
                grouping: $('#text-search-grouping').val()
            },
            success: function (data) {
                response(data);
            }
        });

    },
    minLength: 2,
    search: (event, ui) => {
        $("#searchHitsButtonContent").html('<span class="spinner-border-sm spinner-border"></span> Keresés');
    },
    select: (event, ui) => {
        window.location = ui.item.link;
        return false;
    },
    response: (event, ui) => {
        if (ui.content[0]) {
            const hitCount = ui.content[0].hitCount;
            $("#searchHitsButtonContent").html(`${hitCount} találat <i class="bi bi-caret-right"></i>`);
            $("#noResultAutocomplete").addClass("hidden");
        } else {
            $("#searchHitsButtonContent").html("Nincs találat");
            if ($("#noResult").length == 0) {
                $("#noResultAutocomplete").removeClass("hidden");
                $("#aiSearchLink").attr('href', '/ai-search?textToSearchAi=' + $("#searchInput").val());
            }
        }
    }

}).data("ui-autocomplete")._renderItem = (ul, item) => {
    return itemRender(ul, item);
};

$('#searchInput').on('input', (event) => {
    if (!event.target.value) {
        $("#searchHitsButtonContent").html('<i class="bi bi-search"></i> Keresés');
    }
});

$('#text-search-book').on('change', function (event) {
    $('#searchInput').autocomplete('search');
});

$('#text-search-translation').on('change', function (event) {
    $('#searchInput').autocomplete('search');
});