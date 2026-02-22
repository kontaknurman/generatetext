(function () {
    'use strict';

    tinymce.PluginManager.add('generatetext_rewrite', function (editor) {

        // Only add button to the main content editor, skip excerpt and others
        if (editor.id !== 'content') {
            return;
        }

        // ---- Overlay with progress bar ----
        var progressTimer = null;
        var progressValue = 0;

        function showFixedOverlay(message) {
            removeFixedOverlay();
            var overlay = document.createElement('div');
            overlay.id = 'generatetext-rewrite-overlay';
            overlay.innerHTML =
                '<div class="generatetext-overlay-content">' +
                    '<span class="generatetext-overlay-spinner"></span>' +
                    '<span class="generatetext-overlay-text">' + message + '</span>' +
                '</div>' +
                '<div class="generatetext-progress-bar">' +
                    '<div class="generatetext-progress-fill" id="generatetext-progress-fill"></div>' +
                '</div>';
            document.body.appendChild(overlay);
            startProgress();
            return overlay;
        }

        function updateOverlayText(message) {
            var textEl = document.querySelector('.generatetext-overlay-text');
            if (textEl) textEl.textContent = message;
        }

        function startProgress() {
            progressValue = 0;
            var fill = document.getElementById('generatetext-progress-fill');
            if (!fill) return;
            fill.style.width = '0%';
            // Simulate progress: fast to 30%, slow to 80%
            progressTimer = setInterval(function () {
                if (progressValue < 30) {
                    progressValue += 2;
                } else if (progressValue < 80) {
                    progressValue += 0.3;
                }
                fill.style.width = progressValue + '%';
            }, 100);
        }

        function finishProgress(callback) {
            if (progressTimer) clearInterval(progressTimer);
            var fill = document.getElementById('generatetext-progress-fill');
            if (fill) {
                fill.style.transition = 'width 0.3s ease';
                fill.style.width = '100%';
            }
            setTimeout(function () {
                if (callback) callback();
            }, 350);
        }

        function removeFixedOverlay() {
            if (progressTimer) clearInterval(progressTimer);
            progressTimer = null;
            var existing = document.getElementById('generatetext-rewrite-overlay');
            if (existing) existing.remove();
        }

        // ---- Typewriter effect ----
        function typewriterInsert(text, callback) {
            // Insert a placeholder span to type into
            var placeholderId = 'generatetext-typing-' + Date.now();
            editor.selection.setContent('<span id="' + placeholderId + '"></span>');

            var span = editor.dom.get(placeholderId);
            if (!span) {
                // Fallback: insert all at once
                editor.selection.setContent(text);
                if (callback) callback();
                return;
            }

            var i = 0;
            var chunkSize = Math.max(1, Math.ceil(text.length / 60)); // finish in ~60 steps
            var interval = setInterval(function () {
                var end = Math.min(i + chunkSize, text.length);
                span.textContent = text.substring(0, end);

                i = end;
                if (i >= text.length) {
                    clearInterval(interval);
                    // Unwrap the span, keep only the text node
                    var textNode = editor.getDoc().createTextNode(text);
                    span.parentNode.replaceChild(textNode, span);
                    // Place cursor at end
                    editor.selection.select(textNode, false);
                    editor.selection.collapse(false);
                    if (callback) callback();
                }
            }, 25);
        }

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

            // Save selection range before async call
            var selBookmark = editor.selection.getBookmark(2);

            // Show overlay with progress bar
            showFixedOverlay('AI is rewriting your text...');

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
                if (json.success && json.data && json.data.rewritten) {
                    // Progress to 100%, then start typing
                    updateOverlayText('Inserting text...');
                    finishProgress(function () {
                        // Restore selection and start typewriter
                        editor.selection.moveToBookmark(selBookmark);
                        // Hide spinner, keep overlay while typing
                        var spinner = document.querySelector('.generatetext-overlay-spinner');
                        if (spinner) spinner.style.display = 'none';
                        updateOverlayText('AI is typing...');

                        typewriterInsert(json.data.rewritten, function () {
                            removeFixedOverlay();
                            editor.notificationManager.open({
                                text: 'Text rewritten successfully!',
                                type: 'success',
                                timeout: 3000
                            });
                        });
                    });
                } else {
                    removeFixedOverlay();
                    var msg = (json.data && json.data.message) ? json.data.message : 'Rewrite failed.';
                    editor.notificationManager.open({
                        text: msg,
                        type: 'error',
                        timeout: 5000
                    });
                }
            })
            .catch(function (err) {
                removeFixedOverlay();
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
