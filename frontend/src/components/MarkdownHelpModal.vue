<!--
Modal window with the Markdown help text, shared by the System Settings and
Plugin Administration pages. Open it with the Bootstrap data attributes:
<a href="#" data-bs-toggle="modal" data-bs-target="#markdownHelpModal">
 -->

<template>
<div class="modal fade" id="markdownHelpModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Markdown</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>
                    The text supports
                    <a class="external" href="https://markdown-it.github.io/" target="_blank"
                       rel="noopener noreferrer">Markdown</a>, with "typographer" enabled and these plugins:
                    <a class="external" href="https://github.com/arve0/markdown-it-attrs" target="_blank"
                       rel="noopener noreferrer">attrs</a>
                    (use, for example, with Bootstrap classes "text-primary", "bg-warning"
                    <a class="external" href="https://getbootstrap.com/docs/" target="_blank"
                       rel="noopener noreferrer">etc.</a>),
                    <a class="external"
                       href="https://github.com/markdown-it/markdown-it-emoji/blob/master/lib/data/light.mjs"
                       target="_blank" rel="noopener noreferrer">emoji</a> light,
                    <a class="external" href="https://github.com/markdown-it/markdown-it-mark"
                       target="_blank" rel="noopener noreferrer">mark</a>,
                    <a class="external" href="https://github.com/markdown-it/markdown-it-sub"
                       target="_blank" rel="noopener noreferrer">sub</a>,
                    <a class="external" href="https://github.com/markdown-it/markdown-it-sup"
                       target="_blank" rel="noopener noreferrer">sup</a>,
                    <a class="external" href="https://github.com/markdown-it/markdown-it-ins"
                       target="_blank" rel="noopener noreferrer">ins</a>,
                    <a class="external" href="https://github.com/markdown-it/markdown-it-abbr"
                       rel="noopener noreferrer" target="_blank">abbr</a>.
                </p>
                <p>
                    Tip: You can create external links with an icon like this:
                    <code>[Neucore](https://github.com/tkhamez/neucore)
                    {target="_blank" rel="noopener noreferrer" class="external"}</code>
                </p>
                <p>
                    Note: In the plugin service text fields, single line breaks are preserved.
                </p>
            </div>
        </div>
    </div>
</div>
</template>
