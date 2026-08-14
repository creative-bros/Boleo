<div class="letter-editor-inline">
    <div class="panel__header panel__header--subtle">
        <h3>Editar carta</h3>
        <span>{{ trim(($editingImportedAccount->tower ?: '').' - '.($editingImportedAccount->unit_number ?: ''), ' -') ?: 'Sin unidad' }}</span>
    </div>
    <div class="letter-preview">
        <div class="form-block-title field--full">
            <span>Vista previa de la carta</span>
            <small>Así se ve actualmente. Se actualiza cuando guardas los cambios de abajo.</small>
        </div>
        <iframe
            src="{{ route('settings.letters.show', ['account' => $editingImportedAccount, 'template' => $editingImportedAccount->status, 'inline' => 1]) }}"
            title="Vista previa de la carta"
            class="letter-preview__frame"
        ></iframe>
    </div>
    <form id="letter-edit-form" class="form-grid" method="POST" action="{{ route('settings.imported-accounts.update', $editingImportedAccount) }}" autocomplete="off">
        @csrf
        @method('PUT')
        <input type="hidden" name="redirect_to" value="settings">
        <div class="form-block-title field--full">
            <span>Carta y tabla</span>
            <small>Edita el texto de la carta y las columnas de la base importada desde aquí.</small>
        </div>
        <div class="field field--full">
            <span>Cuerpo editable de la carta</span>
            <small>Escribe directo sobre la hoja, como en Word. Los saltos de párrafo se guardan; negritas/cursivas son solo visuales aquí y no se imprimen en el PDF.</small>
            <textarea name="custom_letter_text" id="custom-letter-text" hidden>{{ $editingBillingAccountLetterBody }}</textarea>
            <div id="letter-word-editor" class="letter-word-editor"></div>
        </div>
        <div class="field field--full">
            <div class="form-block-title">
                <span>Columnas de la tabla</span>
                <small>Agrega, renombra o elimina columnas del registro importado. La unidad y el nombre deben seguir presentes.</small>
            </div>
            <div class="table-wrap">
                <table data-columns-editor>
                    <thead>
                        <tr>
                            <th>Columna</th>
                            <th>Valor</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-columns-body>
                        @foreach ($editingBillingAccountColumns as $index => $column)
                            <tr data-column-row>
                                <td>
                                    <input type="text" name="columns[{{ $index }}][key]" value="{{ $column['key'] ?? '' }}" autocomplete="off">
                                </td>
                                <td>
                                    <input type="text" name="columns[{{ $index }}][value]" value="{{ $column['value'] ?? '' }}" autocomplete="off">
                                </td>
                                <td>
                                    <button class="button button--ghost button--small" type="button" data-remove-column>Quitar</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="form-actions" style="justify-content:flex-start; margin-top:12px;">
                <button class="button button--ghost" type="button" data-add-column>Agregar columna</button>
            </div>
            <template id="editing-column-template">
                <tr data-column-row>
                    <td>
                        <input type="text" name="columns[__INDEX__][key]" value="" autocomplete="off">
                    </td>
                    <td>
                        <input type="text" name="columns[__INDEX__][value]" value="" autocomplete="off">
                    </td>
                    <td>
                        <button class="button button--ghost button--small" type="button" data-remove-column>Quitar</button>
                    </td>
                </tr>
            </template>
        </div>
        <div class="form-actions">
            <a class="button button--ghost" href="{{ route('settings', ['base_import' => $editingImportedAccount->billing_base_import_id]) }}#base-historica-cartas">Cancelar edición</a>
            <button class="button button--primary" type="submit">Guardar carta</button>
        </div>
    </form>
</div>

<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
    (function () {
        const hiddenField = document.getElementById('custom-letter-text');
        const editorContainer = document.getElementById('letter-word-editor');
        const form = document.getElementById('letter-edit-form');

        if (!hiddenField || !editorContainer || !form || typeof Quill === 'undefined') {
            return;
        }

        const quill = new Quill(editorContainer, {
            theme: 'snow',
            modules: {
                toolbar: [['bold', 'italic'], [{ align: [] }], ['clean']],
            },
        });

        const initialParagraphs = hiddenField.value.split(/\n{2,}/).filter((line) => line.trim() !== '');
        quill.setText(initialParagraphs.length ? initialParagraphs.join('\n\n') : hiddenField.value);

        form.addEventListener('submit', () => {
            hiddenField.value = quill.getText().trim();
        });
    })();
</script>
<script>
    (function () {
        const table = document.querySelector('[data-columns-editor]');
        const body = table ? table.querySelector('[data-columns-body]') : null;
        const addButton = document.querySelector('[data-add-column]');
        const template = document.getElementById('editing-column-template');

        if (!body || !addButton || !template) {
            return;
        }

        let nextIndex = body.querySelectorAll('[data-column-row]').length;

        const wireRemoveButtons = (scope) => {
            scope.querySelectorAll('[data-remove-column]').forEach((button) => {
                button.addEventListener('click', () => {
                    const row = button.closest('[data-column-row]');
                    if (row) {
                        row.remove();
                    }
                });
            });
        };

        wireRemoveButtons(body);

        addButton.addEventListener('click', () => {
            const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
            body.insertAdjacentHTML('beforeend', html);
            wireRemoveButtons(body);
        });
    })();
</script>
