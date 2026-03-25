/**
 * Alpine.js stores for admin console: Toast and Confirm modal.
 * Register on alpine:init so they work with Alpine from CDN.
 */
document.addEventListener('alpine:init', () => {
  if (typeof window.Alpine === 'undefined') return;

  window.Alpine.store('toast', {
    visible: false,
    title: '',
    message: '',
    type: 'success', // success | error | info
    timer: null,

    show(title, message, type = 'success', duration = 3000) {
      this.title = title;
      this.message = message;
      this.type = type;
      this.visible = true;
      if (this.timer) clearTimeout(this.timer);
      this.timer = setTimeout(() => {
        this.visible = false;
        this.timer = null;
      }, duration);
    },

    success(title, message, duration) {
      this.show(title, message, 'success', duration);
    },
    error(title, message, duration) {
      this.show(title, message, 'error', duration || 4500);
    },
    info(title, message, duration) {
      this.show(title, message, 'info', duration);
    },
  });

  window.Alpine.store('confirm', {
    visible: false,
    title: '确认操作',
    message: '确定要执行该操作吗？',
    destructive: false,
    confirmLabel: '确定',
    cancelLabel: '取消',
    _resolve: null,

    open(options = {}) {
      this.title = options.title || '确认操作';
      this.message = options.message || '确定要执行该操作吗？';
      this.destructive = options.destructive ?? false;
      this.confirmLabel = options.confirmLabel || '确定';
      this.cancelLabel = options.cancelLabel || '取消';
      this.visible = true;
      return new Promise((resolve) => {
        this._resolve = resolve;
      });
    },

    confirm() {
      this.visible = false;
      if (this._resolve) this._resolve(true);
      this._resolve = null;
    },

    cancel() {
      this.visible = false;
      if (this._resolve) this._resolve(false);
      this._resolve = null;
    },
  });
});
