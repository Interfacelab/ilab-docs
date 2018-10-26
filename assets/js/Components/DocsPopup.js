import DomTools from '../Utils/DomTools';

export default class DocsPopup {
    constructor() {
        this.visible = false;

        this.modal = DomTools.createElement('<div class="ilab-docs-modal ilab-docs-modal-hidden"></div>');
        this.helpContainer = DomTools.createElement('<div class="ilab-docs-ajax-container"></div>');
        this.closeButton = DomTools.createElement('<a href="#" class="ilab-docs-modal-close">Close</a>');

        this.closeButton.addEventListener('click', function(e){
            e.preventDefault();
            this.hide();
            return false;
        }.bind(this));

        this.modal.appendChild(this.helpContainer);

        this.modal.addEventListener('click', function(e) {
            if (e.target == this.modal) {
                this.hide();
            }
        }.bind(this));

        document.querySelectorAll('.ilab-docs-link > a').forEach(function(anchor){
            var targetPage = null;
            var targetDocSet = null;

            console.log(anchor);

            if (anchor.href.indexOf('?') > 0) {
                var queryParams = anchor.href.split('?')[1].split('&');
                console.log(queryParams);
                queryParams.forEach(function(param){
                    if (param.startsWith('doc-page=')) {
                        var params = param.split('=');
                        targetPage = params[1];
                        console.log(targetPage);
                    } else if (param.startsWith('page=')) {
                        var params = param.split('=');
                        if (params[1].startsWith('ilab-docs-')) {
                            targetDocSet = params[1].replace('ilab-docs-', '');
                            console.log(targetDocSet);
                        }
                    }
                });

                if ((targetDocSet != null) && (targetPage != null)) {
                    anchor.addEventListener('click', function(e){
                        e.preventDefault();

                        this.show(targetDocSet, targetPage, function(docElement){
                            console.log('page loaded ...');
                            this.hijackLinks('a', docElement);
                        }.bind(this));

                        return false;
                    }.bind(this));
                }
            }

        }.bind(this));
    }

    show(targetDocSet, targetPage, callback) {
        if (this.visible) {
            return;
        }

        this.visible = true;

        this.loadPage(targetDocSet, targetPage, function(docElement){
            if (callback) {
                callback(docElement);
            }

            document.body.appendChild(this.modal);

            setTimeout(function(){
                this.modal.classList.remove('ilab-docs-modal-hidden');
            }.bind(this), 1);
        }.bind(this));
    }

    hide() {
        if (!this.visible) {
            return;
        }

        this.modal.classList.add('ilab-docs-modal-hidden');
        setTimeout(function(){
            document.body.removeChild(this.modal);
            this.visible = false;
        }.bind(this), 500);
    }

    loadPage(targetDocSet, targetPage, callback) {
        if (!this.visible) {
            return;
        }

        jQuery.post(ajaxurl, { "action": "ilab_render_doc_page", "doc-set": targetDocSet, "doc-page": targetPage }, function(response) {
            this.helpContainer.innerHTML = response.html;
            if (callback) {
                callback(this.helpContainer);
            }

            this.hijackSearch();

            this.helpContainer.appendChild(this.closeButton);
        }.bind(this));
    }

    hijackLinks(selector, docElement) {
        docElement.querySelectorAll(selector).forEach(function(anchor){
            var targetPage = null;
            var targetDocSet = null;

            if (anchor.href.indexOf('?') > 0) {
                var queryParams = anchor.href.split('?')[1].split('&');
                console.log(queryParams);
                queryParams.forEach(function(param){
                    if (param.startsWith('doc-page=')) {
                        var params = param.split('=');
                        targetPage = params[1];
                        console.log(targetPage);
                    } else if (param.startsWith('page=')) {
                        var params = param.split('=');
                        if (params[1].startsWith('ilab-docs-')) {
                            targetDocSet = params[1].replace('ilab-docs-', '');
                            console.log(targetDocSet);
                        }
                    }
                });

                if ((targetDocSet != null) && (targetPage != null)) {
                    anchor.addEventListener('click', function(e){
                        e.preventDefault();

                        console.log('hotclick', targetDocSet, targetPage);

                        this.loadPage(targetDocSet, targetPage, function(docElement){
                            console.log('loaded?', docElement);
                            this.hijackLinks('a', docElement);
                        }.bind(this));

                        return false;
                    }.bind(this));
                }
            }
        }.bind(this));
    }

    hijackSearch() {
        var searchContainer = document.querySelector('.ilab-docs-search');
        if (searchContainer == null) {
            return;
        }

        var form = searchContainer.querySelector('form');
        form.addEventListener('submit', function(e){
           e.preventDefault();
           return false;
        });

        var searchInput = form.querySelector('input[type=search]');

        var button = searchContainer.querySelector('input[type=submit]');
        button.addEventListener('click', function(e){
           e.preventDefault();

            jQuery.post(ajaxurl, { "action": "ilab_render_doc_page", "search-text": searchInput.value, "doc-page": "index" }, function(response) {
                console.log(response);
                this.helpContainer.innerHTML = response.html;
                this.hijackLinks('a', this.helpContainer);
                this.hijackSearch();

                this.helpContainer.appendChild(this.closeButton);
            }.bind(this));


           return false;
        }.bind(this));
    }
}