(function () {
    'use strict';

    function countCharacters(value) {
        return Array.from ? Array.from(value).length : value.length;
    }

    var autoFocusTarget = document.querySelector('[data-auto-focus]');
    if (autoFocusTarget) {
        autoFocusTarget.focus();
    }

    var replyField = document.getElementById('reply-content');
    var replyCount = document.getElementById('reply-count');
    if (replyField && replyCount) {
        var updateReplyCount = function () {
            replyCount.textContent = countCharacters(replyField.value) + '/2000';
        };
        replyField.addEventListener('input', updateReplyCount);
        updateReplyCount();
    }

    var selectAll = document.querySelector('[data-select-all]');
    var rowSelects = Array.prototype.slice.call(document.querySelectorAll('[data-row-select]'));
    var batchToolbar = document.querySelector('[data-batch-toolbar]');
    var batchForm = document.querySelector('[data-batch-form]');
    var batchActionInput = document.querySelector('[data-batch-action-input]');
    var batchActionButtons = Array.prototype.slice.call(document.querySelectorAll('[data-batch-action]'));
    var batchCancel = document.querySelector('[data-batch-cancel]');
    var selectedCount = document.querySelector('[data-selected-count]');
    var pendingBatchAction = '';

    function updateBatchState() {
        var checked = rowSelects.filter(function (input) { return input.checked; }).length;
        if (selectedCount) {
            selectedCount.textContent = checked > 0 ? '已选择 ' + checked + ' 条' : '勾选留言后可批量处理';
        }
        if (batchToolbar) {
            batchToolbar.classList.toggle('is-batch-active', checked > 0);
        }
        if (selectAll) {
            selectAll.checked = checked > 0 && checked === rowSelects.length;
            selectAll.indeterminate = checked > 0 && checked < rowSelects.length;
        }
        batchActionButtons.forEach(function (button) {
            button.disabled = checked === 0;
        });
        if (batchCancel) {
            batchCancel.disabled = checked === 0;
        }
        rowSelects.forEach(function (input) {
            var row = input.closest ? input.closest('tr') : null;
            if (row) {
                row.classList.toggle('is-batch-selected', input.checked);
            }
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowSelects.forEach(function (input) { input.checked = selectAll.checked; });
            updateBatchState();
        });
    }
    rowSelects.forEach(function (input) {
        input.addEventListener('change', updateBatchState);
    });
    batchActionButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            pendingBatchAction = button.getAttribute('data-batch-action') || '';
            if (batchActionInput) {
                batchActionInput.value = pendingBatchAction;
            }
        });
    });
    if (batchCancel) {
        batchCancel.addEventListener('click', function () {
            rowSelects.forEach(function (input) { input.checked = false; });
            pendingBatchAction = '';
            if (batchActionInput) {
                batchActionInput.value = '';
            }
            updateBatchState();
            if (selectAll) {
                selectAll.focus();
            }
        });
    }
    if (batchForm) {
        batchForm.addEventListener('submit', function (event) {
            var checked = rowSelects.filter(function (input) { return input.checked; }).length;
            if (checked === 0 || pendingBatchAction === '') {
                event.preventDefault();
                return;
            }
            var labels = {
                approve: '审核通过',
                reject: '审核驳回',
                show: '设为显示',
                hide: '设为隐藏',
                soft_delete: '移至回收站',
                restore: '从回收站恢复'
            };
            var message = '确认将选中的 ' + checked + ' 条留言执行“' + labels[pendingBatchAction] + '”吗？';
            if (pendingBatchAction === 'soft_delete') {
                message += ' 留言将从公开页面移除，但之后可以恢复。';
            }
            if (!window.confirm(message)) {
                event.preventDefault();
                pendingBatchAction = '';
                if (batchActionInput) {
                    batchActionInput.value = '';
                }
                return;
            }
            batchActionButtons.forEach(function (button) { button.disabled = true; });
            if (batchCancel) {
                batchCancel.disabled = true;
            }
        });
    }
    updateBatchState();

    Array.prototype.slice.call(document.querySelectorAll('form[data-confirm]')).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    var publishButton = document.querySelector('[data-publish-reply]');
    if (publishButton) {
        publishButton.addEventListener('click', function (event) {
            if (!window.confirm('确认发布这条回复吗？留言的审核状态和展示状态不会自动改变。')) {
                event.preventDefault();
            }
        });
    }
}());
