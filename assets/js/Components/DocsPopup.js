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

            var queryParams = anchor.href.split('&');
            queryParams.forEach(function(param){
                if (param.startsWith('doc-page=')) {
                    var params = param.split('=');
                    targetPage = params[1];
                    return;
                }
            });

            if (targetPage != null) {
                anchor.addEventListener('click', function(e){
                    e.preventDefault();

                    this.show(targetPage, function(docElement){
                        this.hijackLinks('a', docElement);
                    }.bind(this));

                    return false;
                }.bind(this));
            }
        }.bind(this));
    }

    show(targetPage, callback) {
        if (this.visible) {
            return;
        }

        this.visible = true;

        this.loadPage(targetPage, function(docElement){
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

    loadPage(targetPage, callback) {
        if (!this.visible) {
            return;
        }

        jQuery.post(ajaxurl, { "action": "ilab_render_doc_page", "doc-page": targetPage }, function(response) {
            this.helpContainer.innerHTML = response.html;
            if (callback) {
                callback(this.helpContainer);
            }

            this.helpContainer.appendChild(this.closeButton);
        }.bind(this));
    }

    hijackLinks(selector, docElement) {
        docElement.querySelectorAll(selector).forEach(function(anchor){
            var targetPage = null;

            var queryParams = anchor.href.split('&');
            queryParams.forEach(function(param){
                if (param.startsWith('doc-page=')) {
                    var params = param.split('=');
                    targetPage = params[1];
                    return;
                }
            });

            if (targetPage != null) {
                anchor.addEventListener('click', function(e){
                    e.preventDefault();

                    this.loadPage(targetPage, function(nextDocElement){
                        this.hijackLinks('a', nextDocElement);
                    }.bind(this));

                    return false;
                }.bind(this));
            }
        }.bind(this));
    }
}