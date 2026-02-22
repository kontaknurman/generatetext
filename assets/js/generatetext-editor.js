(function () {
    'use strict';

    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const { createElement: el, useState, useEffect, useCallback, Fragment } = wp.element;
    const { Button, PanelBody, Spinner, Notice } = wp.components;
    const { useSelect, useDispatch } = wp.data;
    const { registerFormatType, insert, remove, create } = wp.richText;
    const { BlockControls, RichTextToolbarButton } = wp.blockEditor;

    const AJAX_URL = generatetextData.ajaxUrl;
    const NONCE = generatetextData.nonce;

    // Fixed-position overlay for rewrite notifications (visible during scroll)
    function showRewriteOverlay(message) {
        removeRewriteOverlay();
        var overlay = document.createElement('div');
        overlay.id = 'generatetext-rewrite-overlay';
        overlay.innerHTML = '<span class="generatetext-overlay-spinner"></span> ' + message;
        document.body.appendChild(overlay);
    }

    function removeRewriteOverlay() {
        var existing = document.getElementById('generatetext-rewrite-overlay');
        if (existing) {
            existing.remove();
        }
    }

    // Helper: AJAX POST
    function apiPost(action, data) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', NONCE);
        for (const [key, val] of Object.entries(data)) {
            formData.append(key, val);
        }
        return fetch(AJAX_URL, { method: 'POST', body: formData })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json.success) {
                    throw new Error(json.data?.message || 'Request failed');
                }
                return json.data;
            });
    }

    // Helper: Get post content as plain text
    function getPostContent() {
        var blocks = wp.data.select('core/block-editor').getBlocks();
        var content = '';
        blocks.forEach(function (block) {
            if (block.attributes && block.attributes.content) {
                var div = document.createElement('div');
                div.innerHTML = block.attributes.content;
                content += div.textContent + '\n';
            }
            if (block.innerBlocks) {
                block.innerBlocks.forEach(function (inner) {
                    if (inner.attributes && inner.attributes.content) {
                        var d = document.createElement('div');
                        d.innerHTML = inner.attributes.content;
                        content += d.textContent + '\n';
                    }
                });
            }
        });
        return content.trim();
    }

    // ========== Sidebar Panel ==========
    function GenerateTextSidebar() {
        var postId = useSelect(function (select) {
            return select('core/editor').getCurrentPostId();
        });

        var _tagsState = useState(false);
        var loadingTags = _tagsState[0], setLoadingTags = _tagsState[1];
        var _catState = useState(false);
        var loadingCats = _catState[0], setLoadingCats = _catState[1];
        var _titleState = useState(false);
        var loadingTitle = _titleState[0], setLoadingTitle = _titleState[1];
        var _noticeState = useState(null);
        var notice = _noticeState[0], setNotice = _noticeState[1];

        var editPost = useDispatch('core/editor').editPost;

        function showNotice(msg, type) {
            setNotice({ message: msg, type: type || 'info' });
            setTimeout(function () { setNotice(null); }, 5000);
        }

        // Generate Tags
        function handleGenerateTags() {
            var content = getPostContent();
            if (!content) {
                showNotice('Please write some content first.', 'error');
                return;
            }
            setLoadingTags(true);
            apiPost('generatetext_tags', { content: content, post_id: postId || 0 })
                .then(function (data) {
                    showNotice('Tags generated: ' + data.tags.join(', '), 'success');
                    // Refresh tags in editor
                    if (wp.data.dispatch('core/editor').editPost) {
                        wp.data.dispatch('core').invalidateResolution('getEntityRecord', ['taxonomy', 'post_tag']);
                    }
                    // Force sidebar refresh
                    wp.data.dispatch('core/editor').editPost({ meta: { _generatetext_refresh: Date.now() } });
                })
                .catch(function (err) { showNotice(err.message, 'error'); })
                .finally(function () { setLoadingTags(false); });
        }

        // Suggest Categories
        function handleSuggestCategories() {
            var content = getPostContent();
            if (!content) {
                showNotice('Please write some content first.', 'error');
                return;
            }
            setLoadingCats(true);
            apiPost('generatetext_categories', { content: content })
                .then(function (data) {
                    var catIds = data.categories.map(function (c) { return parseInt(c.id, 10); });
                    var catNames = data.categories.map(function (c) { return c.name; });
                    // Use wp.data.dispatch directly to ensure categories are updated
                    wp.data.dispatch('core/editor').editPost({ categories: catIds });
                    showNotice('Categories set: ' + catNames.join(', '), 'success');
                })
                .catch(function (err) { showNotice(err.message, 'error'); })
                .finally(function () { setLoadingCats(false); });
        }

        // Generate Title
        function handleGenerateTitle() {
            var content = getPostContent();
            if (!content) {
                showNotice('Please write some content first.', 'error');
                return;
            }
            setLoadingTitle(true);
            apiPost('generatetext_title', { content: content })
                .then(function (data) {
                    editPost({ title: data.title });
                    showNotice('Title generated!', 'success');
                })
                .catch(function (err) { showNotice(err.message, 'error'); })
                .finally(function () { setLoadingTitle(false); });
        }

        return el(
            Fragment,
            null,
            el(
                PluginSidebarMoreMenuItem,
                { target: 'generatetext-sidebar', icon: 'edit' },
                'GenerateText'
            ),
            el(
                PluginSidebar,
                {
                    name: 'generatetext-sidebar',
                    title: 'GenerateText AI',
                    icon: 'edit',
                },
                notice && el(
                    Notice,
                    {
                        status: notice.type,
                        isDismissible: true,
                        onRemove: function () { setNotice(null); },
                    },
                    notice.message
                ),
                // Tags
                el(
                    PanelBody,
                    { title: 'Generate Tags', initialOpen: true },
                    el('p', { className: 'generatetext-description' }, 'Generate relevant tags based on your article content. Uses existing tags when possible.'),
                    el(
                        Button,
                        {
                            variant: 'secondary',
                            onClick: handleGenerateTags,
                            disabled: loadingTags,
                            className: 'generatetext-btn',
                        },
                        loadingTags ? el(Spinner, null) : null,
                        loadingTags ? ' Generating...' : 'Generate Tags'
                    )
                ),
                // Categories
                el(
                    PanelBody,
                    { title: 'Suggest Category', initialOpen: true },
                    el('p', { className: 'generatetext-description' }, 'Suggest the best category from your existing categories.'),
                    el(
                        Button,
                        {
                            variant: 'secondary',
                            onClick: handleSuggestCategories,
                            disabled: loadingCats,
                            className: 'generatetext-btn',
                        },
                        loadingCats ? el(Spinner, null) : null,
                        loadingCats ? ' Analyzing...' : 'Suggest Category'
                    )
                ),
                // Title
                el(
                    PanelBody,
                    { title: 'Generate Title', initialOpen: true },
                    el('p', { className: 'generatetext-description' }, 'Generate or rewrite the post title based on your content.'),
                    el(
                        Button,
                        {
                            variant: 'secondary',
                            onClick: handleGenerateTitle,
                            disabled: loadingTitle,
                            className: 'generatetext-btn',
                        },
                        loadingTitle ? el(Spinner, null) : null,
                        loadingTitle ? ' Generating...' : 'Generate Title'
                    )
                )
            )
        );
    }

    registerPlugin('generatetext', {
        render: GenerateTextSidebar,
        icon: 'edit',
    });

    // ========== Inline Rewrite (Floating toolbar button) ==========
    var REWRITE_FORMAT = 'generatetext/rewrite';

    registerFormatType(REWRITE_FORMAT, {
        title: 'AI Rewrite',
        tagName: 'span',
        className: 'generatetext-rewrite',
        edit: function RewriteButton(props) {
            var isActive = props.isActive;
            var value = props.value;
            var onChange = props.onChange;

            var _loadState = useState(false);
            var loading = _loadState[0], setLoading = _loadState[1];

            var selectedText = value.text.slice(value.start, value.end);
            if (!selectedText || selectedText.length < 3) {
                return null;
            }

            function handleRewrite() {
                if (loading) return;
                setLoading(true);

                // Show a fixed overlay notification visible during scroll
                showRewriteOverlay('AI is rewriting your text...');

                apiPost('generatetext_rewrite', { text: selectedText })
                    .then(function (data) {
                        removeRewriteOverlay();
                        var newValue = insert(
                            value,
                            create({ text: data.rewritten }),
                            value.start,
                            value.end
                        );
                        onChange(newValue);
                        wp.data.dispatch('core/notices').createSuccessNotice(
                            'Text rewritten successfully!',
                            { type: 'snackbar' }
                        );
                    })
                    .catch(function (err) {
                        removeRewriteOverlay();
                        wp.data.dispatch('core/notices').createErrorNotice(
                            'GenerateText: ' + err.message,
                            { type: 'snackbar' }
                        );
                    })
                    .finally(function () {
                        setLoading(false);
                    });
            }

            return el(
                RichTextToolbarButton,
                {
                    icon: 'editor-paste-word',
                    title: loading ? 'Rewriting...' : 'AI Rewrite',
                    onClick: handleRewrite,
                    isActive: loading,
                }
            );
        },
    });
})();
