(function () {
    'use strict';

    tinymce.PluginManager.add('generatetext_rewrite', function (editor) {

        function doRewrite() {
            var selectedText = editor.selection.getContent({ format: 'text' });
            if (!selectedText || selectedText.length < 3) {
                editor.notificationManager.open({
                    text: 'Select some text to rewrite first.',
                    type: 'warning',
                    timeout: 3000
                });
                return;
            }

            // Show loading notification
            var loadingNotice = editor.notificationManager.open({
                text: 'AI is rewriting your text...',
                type: 'info',
                timeout: 0
            });

            // Disable button while processing
            var btn = editor.controlManager && editor.controlManager.get('generatetext_rewrite');

            var data = new FormData();
            data.append('action', 'generatetext_rewrite');
            data.append('nonce', window.generatetextData.nonce);
            data.append('text', selectedText);

            fetch(window.generatetextData.ajaxUrl, {
                method: 'POST',
                body: data
            })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                loadingNotice.close();

                if (json.success && json.data && json.data.rewritten) {
                    // Preserve selection bookmark, replace content
                    editor.selection.setContent(json.data.rewritten);
                    editor.notificationManager.open({
                        text: 'Text rewritten successfully!',
                        type: 'success',
                        timeout: 3000
                    });
                } else {
                    var msg = (json.data && json.data.message) ? json.data.message : 'Rewrite failed.';
                    editor.notificationManager.open({
                        text: msg,
                        type: 'error',
                        timeout: 5000
                    });
                }
            })
            .catch(function (err) {
                loadingNotice.close();
                editor.notificationManager.open({
                    text: 'Network error: ' + err.message,
                    type: 'error',
                    timeout: 5000
                });
            });
        }

        // Register toolbar button
        editor.addButton('generatetext_rewrite', {
            title: 'AI Rewrite',
            icon: 'dashicon dashicons-edit-large',
            cmd: 'generatetext_rewrite_cmd',
            onPostRender: function () {
                var self = this;
                // Enable/disable button based on text selection
                editor.on('NodeChange', function () {
                    var hasSelection = editor.selection.getContent({ format: 'text' }).length >= 3;
                    self.active(hasSelection);
                });
            }
        });

        // Register command
        editor.addCommand('generatetext_rewrite_cmd', doRewrite);

        // Also add to context menu if available
        if (editor.addMenuItem) {
            editor.addMenuItem('generatetext_rewrite', {
                text: 'AI Rewrite',
                icon: 'paste',
                context: 'format',
                cmd: 'generatetext_rewrite_cmd'
            });
        }
    });
})();
