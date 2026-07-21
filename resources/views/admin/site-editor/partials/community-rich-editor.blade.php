<div class="community-rich-field" data-community-rich-field>
    <span>Contenido enriquecido</span>
    <div class="community-rich-toolbar" role="toolbar" aria-label="Formato del contenido">
        <button type="button" data-rich-command="bold" aria-label="Negrita"><strong>B</strong></button>
        <button type="button" data-rich-command="italic" aria-label="Cursiva"><em>I</em></button>
        <button type="button" data-rich-command="underline" aria-label="Subrayado"><u>U</u></button>
        <button type="button" data-rich-command="formatBlock" data-rich-value="h2">Título</button>
        <button type="button" data-rich-command="insertUnorderedList">Lista</button>
        <button type="button" data-rich-link>Link</button>
        <button type="button" data-rich-command="removeFormat">Limpiar</button>
    </div>
    <div
        class="community-rich-editor"
        id="{{ $editorId }}"
        contenteditable="true"
        role="textbox"
        aria-multiline="true"
        data-community-rich-editor
        data-placeholder="Escribe el post completo aquí..."
    >{!! $bodyHtml !!}</div>
    <input name="body" type="hidden" value="{{ $bodyHtml }}" data-community-rich-input>
</div>
