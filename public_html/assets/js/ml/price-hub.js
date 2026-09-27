/* ══════════════════════════════════════════════
   Price hub — clipboard copy
   ══════════════════════════════════════════════ */
(function () {
  'use strict'

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.phub-copy')
    if (!btn) return
    e.stopPropagation()
    var bid = btn.getAttribute('data-batch')
    if (!bid || typeof navigator.clipboard === 'undefined') return
    navigator.clipboard.writeText(bid).then(function () {
      var orig = btn.innerHTML
      btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor"><path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0"/></svg>'
      setTimeout(function () { btn.innerHTML = orig }, 1500)
    }).catch(function () {})
  })
})()
