class QuickChapterSelector {

    translation = null;

    constructor(translation = null) {
        this.translation = translation;
    }

    init() {
        let books;
        const corpusSelectorButton = document.getElementById("corpusSelectorButton");
        const bookSelectorButton = document.getElementById('bookSelectorButton');
        const bookSelector = document.getElementById('bookSelector');
        const bookSelectorList = document.getElementById("bookSelectorList");
        const chapterSelector = document.getElementById('chapterSelector');
        const chapterSelectorButton = document.getElementById('chapterSelectorButton');
        const chapterSelectorList = document.getElementById("chapterSelectorList");

        const spinner = document.getElementById("selectorSpinner");

        function showSpinner(show = true) { show ? spinner.classList.remove('hideSpinner') : spinner.classList.add('hideSpinner'); }

        const dropdownItems = document.querySelectorAll('#corpusSelector .dropdown-item');
        dropdownItems.forEach(item => {
            item.addEventListener('click', async (event) => {
                bookSelector.classList.add('hidden');
                chapterSelector.classList.add('hidden');
                bookSelectorButton.innerHTML = "Könyv <span class=\"caret\"></span>";
                chapterSelectorButton.innerHTML = "Fejezet <span class=\"caret\"></span>";
                showSpinner();
                const value = item.getAttribute("data-value");
                const itemHtmlValue = item.innerHTML;
                corpusSelectorButton.innerHTML = `<strong>${itemHtmlValue}</strong>`;
                const apiLink = this.translation ? `/api/books/${this.translation}` : '/api/books';
                const response = await fetch(apiLink);
                const bookResponse = await response.json();
                books = bookResponse.books;
                const filteredBooks = books.filter(book => book.corpus == value);
                while (bookSelectorList.firstChild) {
                    bookSelectorList.removeChild(bookSelectorList.firstChild);
                }
                filteredBooks.forEach(book => {
                    const a = document.createElement('a');
                    a.href = '#';
                    a.setAttribute('data-usx', book.usx_code);
                    a.setAttribute('data-abbrev', book.abbrev);
                    a.classList.add('dropdown-item');
                    a.innerHTML = `<strong>${book.abbrev}</strong> <small>${book.name}</small>`;
                    a.addEventListener('click', async (event) => {
                        chapterSelector.classList.add('hidden');
                        showSpinner();
                        while (chapterSelectorList.firstChild) {
                            chapterSelectorList.removeChild(chapterSelectorList.firstChild);
                        }
                        const usx = a.getAttribute("data-usx");
                        const selectedBook = books.find(book => book.usx_code == usx);
                        bookSelectorButton.innerHTML = `<strong>${selectedBook.abbrev}</strong>`;
                        for (let i = 1; i <= selectedBook.chapterCount; i++) {
                            const a = document.createElement('a');
                            a.classList.add('dropdown-item');
                            let aLink = `/${selectedBook.abbrev}${i}`;
                            if (this.translation !== null) {
                                aLink = `/${this.translation}` + aLink;
                            }
                            a.href = aLink;
                            a.textContent = i;
                            chapterSelectorList.appendChild(a);
                        }
                        chapterSelector.classList.remove('hidden');

                        showSpinner(false);
                    });
                    bookSelectorList.appendChild(a);
                });
                showSpinner(false);
                bookSelector.classList.remove('hidden');

            });
        }
        );

    }
}

export default function initQuickChapterSelector(translation = null) {
    const quickChapterSelector = new QuickChapterSelector(translation);
    quickChapterSelector.init();
}