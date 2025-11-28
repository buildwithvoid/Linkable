const { createElement: el } = wp.element;
const { useSelect, useDispatch } = wp.data;
const { PluginSidebar } = wp.editPost;
const { FormTokenField, PanelBody } = wp.components;

const META_KEY = 'linkable_tags';

const Sidebar = () => {
    const meta = useSelect((select) =>
            select('core/editor').getEditedPostAttribute('meta'),
        []
    );

    const { editPost } = useDispatch('core/editor');

    let currentTags = [];
    try {
        currentTags = JSON.parse(meta[META_KEY] || '[]');
    } catch (e) {
        console.warn('Parsing error:', e);
    }

    const onChange = (newTags) => {
        const json = JSON.stringify(newTags);
        editPost({ meta: { [META_KEY]: json } });
    };

    return el(
        PluginSidebar,
        { name: 'linkable-sidebar', icon: 'admin-links', title: 'Linkable' },
        el(
            PanelBody,
            { title: 'Tags / phrases', initialOpen: true },
            el(FormTokenField, {
                label: 'Indtast tags',
                value: currentTags,
                onChange: onChange,
                placeholder: 'Skriv og tryk enter...'
            })
        )
    );
};

wp.plugins.registerPlugin('linkable-sidebar', {
    render: Sidebar
});