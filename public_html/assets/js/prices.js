/* ══════════════════════════════════════════════
   Price change wizard — upload, preview, process
   Requires: window.PRICE_CONFIG { urlRoot, csrf, storeName }
   ══════════════════════════════════════════════ */
(function () {
  'use strict'

  var cfg = window.PRICE_CONFIG
  if (!cfg) return

  var uploadForm = document.getElementById('uploadForm')
  var stepUpload = document.getElementById('stepUpload')
  var stepPreview = document.getElementById('stepPreview')
  var stepProcessing = document.getElementById('stepProcessing')
  var progressFill = document.getElementById('progressFill')
  var progressText = document.getElementById('progressText')
  var progressCounter = document.getElementById('progressCounter')
  var progressPercent = document.getElementById('progressPercent')
  var processLogs = document.getElementById('processLogs')
  var btnResume = document.getElementById('btnResume')
  var pricesConfirmModal = document.getElementById('pricesConfirmModal')
  var modalTotal = document.getElementById('modalTotal')
  var modalStore = document.getElementById('modalStore')
  var btnConfirmStart = document.getElementById('btnConfirmStart')
  var btnCancelConfirm = document.getElementById('btnCancelConfirm')
  var btnExecute = document.getElementById('btnExecute')
  var btnBackUpload = document.getElementById('btnBackUpload')
  var previewColSku = document.getElementById('previewColSku')
  var previewColPrice = document.getElementById('previewColPrice')
  var previewTotal = document.getElementById('previewTotal')
  var fileValidation = document.getElementById('fileValidation')
  var stepIndicator1 = document.getElementById('stepIndicator1')
  var stepIndicator2 = document.getElementById('stepIndicator2')
  var stepIndicator3 = document.getElementById('stepIndicator3')
  var stepIndicator4 = document.getElementById('stepIndicator4')
  var stepConnector1 = document.getElementById('stepConnector1')
  var stepConnector2 = document.getElementById('stepConnector2')
  var stepConnector3 = document.getElementById('stepConnector3')

  var storeName = cfg.storeName || ''
  var currentStep = 1
  var steps = [
    { el: stepUpload, num: 1 },
    { el: stepPreview, num: 2 },
    { el: stepProcessing, num: 3 }
  ]

  function setActiveStep(num) {
    var indicators = [stepIndicator1, stepIndicator2, stepIndicator3, stepIndicator4]
    var connectors = [stepConnector1, stepConnector2, stepConnector3]
    indicators.forEach(function (el, i) {
      if (!el) return
      el.classList.toggle('active', i < num)
      el.classList.toggle('completed', i < num - 1)
    })
    connectors.forEach(function (con, i) {
      if (!con) return
      con.classList.toggle('done', i < num - 1)
    })
  }

  function showStep(step) {
    steps.forEach(function (s) {
      if (!s.el) return
      if (s.num === step) {
        s.el.style.display = 'block'
        s.el.classList.remove('pw-step-leave')
        s.el.classList.add('pw-step-enter')
      } else if (s.num === currentStep) {
        s.el.classList.remove('pw-step-enter')
        s.el.classList.add('pw-step-leave')
        setTimeout(function () { s.el.style.display = 'none' }, 250)
      } else {
        s.el.style.display = 'none'
      }
    })
    currentStep = step
    setActiveStep(step)
  }

  function showValidation(type, msg) {
    if (!fileValidation) return
    fileValidation.style.display = 'block'
    fileValidation.className = 'file-validation file-' + type
    fileValidation.innerHTML = ''
    var el = document.createElement('span')
    el.className = 'fv-icon'
    el.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + (type === 'success' ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>' : (type === 'error' ? '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>' : '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>')) + '</svg>'
    fileValidation.appendChild(el)
    var text = document.createElement('span')
    text.textContent = msg
    fileValidation.appendChild(text)
  }

  function hideValidation() {
    if (!fileValidation) return
    fileValidation.style.display = 'none'
  }

  function formatTime(seconds) {
    if (seconds < 60) return Math.round(seconds) + ' segundos'
    var m = Math.floor(seconds / 60)
    var s = Math.round(seconds % 60)
    return m + ' min ' + s + ' s'
  }

  function updateEta(processed, total, startTime) {
    var el = document.getElementById('processEta')
    if (!el) return
    var elapsed = (Date.now() - startTime) / 1000
    if (processed === 0 || elapsed < 1) {
      el.textContent = 'Tiempo estimado: calculando…'
      return
    }
    var rate = processed / elapsed
    var remaining = (total - processed) / rate
    el.textContent = 'Tiempo estimado: ~' + formatTime(remaining) + ' restantes'
  }

  function esc(s) {
    var d = document.createElement('div')
    d.textContent = s
    return d.innerHTML
  }

  if (btnResume) {
    btnResume.addEventListener('click', function () {
      var banner = this.closest('.incomplete-banner')
      if (banner) banner.style.display = 'none'
      var el = document.getElementById('stepProcessingResume') || document.getElementById('stepProcessing')
      if (el) el.style.display = 'block'
      var total = parseInt(this.getAttribute('data-total'), 10)
      if (total > 0) startBatch(total, true)
    })
  }

  if (!uploadForm) return

  var drop = document.getElementById('fileDrop')
  var input = document.getElementById('pricesFile')
  var btn = document.getElementById('uploadBtn')
  var fileName = document.getElementById('fileName')

  input.addEventListener('change', function () {
    if (this.files && this.files[0]) {
      var name = this.files[0].name
      var size = (this.files[0].size / 1024).toFixed(1)
      fileName.textContent = name + ' (' + size + ' KB)'
      validateFile(this.files[0])
    } else {
      fileName.textContent = ''
      hideValidation()
      btn.disabled = true
    }
  })

  function validateFile(file) {
    hideValidation()
    if (!file) { btn.disabled = true; return }

    var ext = file.name.split('.').pop().toLowerCase()
    if (ext !== 'xlsx' && ext !== 'csv') {
      showValidation('error', 'Formato no soportado. Usa un archivo .xlsx o .csv.')
      btn.disabled = true
      return
    }

    if (file.size > 10 * 1024 * 1024) {
      showValidation('error', 'Archivo demasiado grande. Máximo 10 MB.')
      btn.disabled = true
      return
    }

    if (typeof showToast === 'function') {
      showToast('Archivo cargado: ' + file.name, 'success', 3000)
    }
    btn.disabled = false
  }

  if (drop) {
    drop.addEventListener('dragover', function (e) { e.preventDefault(); this.classList.add('dragover') })
    drop.addEventListener('dragleave', function () { this.classList.remove('dragover') })
    drop.addEventListener('drop', function (e) {
      e.preventDefault()
      this.classList.remove('dragover')
      if (e.dataTransfer.files && e.dataTransfer.files[0]) {
        input.files = e.dataTransfer.files
        fileName.textContent = e.dataTransfer.files[0].name
        validateFile(e.dataTransfer.files[0])
      }
    })
  }

  uploadForm.addEventListener('submit', function (e) {
    e.preventDefault()
    if (!input.files || !input.files[0]) return
    btn.disabled = true
    btn.classList.add('pw-btn-loading')

    var fd = new FormData(uploadForm)
    fd.append('prices_file', input.files[0])

    fetch(cfg.urlRoot + '/connections/preparePrices', {
      method: 'POST',
      body: fd,
    })
    .then(function (r) { return r.json() })
    .then(function (data) {
      btn.disabled = false
      btn.classList.remove('pw-btn-loading')

      if (data.error) {
        if (typeof showToast === 'function') {
          showToast(data.error, 'error', 6000)
          if (data.retry_after) {
            var minutes = Math.ceil(data.retry_after / 60)
            setTimeout(function () {
              showToast('Por seguridad, solo puedes importar 3 archivos cada 60 minutos. Intenta en ' + minutes + ' minuto(s).', 'warning', 8000)
            }, 1000)
          }
        }
        return
      }

      if (data.validation_errors && data.validation_errors.length) {
        if (typeof showToast === 'function') {
          showToast('Archivo analizado con advertencias', 'warning', 5000)
        }
      } else {
        if (typeof showToast === 'function') {
          showToast('Archivo analizado correctamente: ' + data.total + ' items encontrados.', 'success', 4000)
        }
      }

      if (previewColSku && data.col_sku) previewColSku.textContent = data.col_sku
      if (previewColPrice && data.col_price) previewColPrice.textContent = data.col_price
      if (previewTotal) previewTotal.textContent = data.total

      var tableWrap = document.getElementById('previewTableWrap')
      var tbody = document.getElementById('previewTbody')
      var more = document.getElementById('previewMore')
      if (tableWrap && tbody && data.preview_rows && data.preview_rows.length) {
        tbody.innerHTML = ''
        data.preview_rows.forEach(function (row) {
          var tr = document.createElement('tr')
          tr.innerHTML = '<td>' + esc(row.sku) + '</td><td>' + esc(row.price) + '</td>'
          tbody.appendChild(tr)
        })
        var extra = data.total - data.preview_rows.length
        if (extra > 0) {
          more.textContent = 'y ' + extra + ' filas más'
          more.style.display = 'block'
        } else {
          more.style.display = 'none'
        }
        tableWrap.style.display = 'block'
      }

      showStep(2)
    })
    .catch(function (err) {
      console.error('preparePrices error:', err)
      showValidation('error', 'Error al analizar: ' + err.message)
      btn.disabled = false
      btn.classList.remove('pw-btn-loading')
    })
  })

  if (btnBackUpload) {
    btnBackUpload.addEventListener('click', function () {
      hideValidation()
      showStep(1)
    })
  }

  if (btnExecute) {
    btnExecute.addEventListener('click', function () {
      var total = parseInt(previewTotal ? previewTotal.textContent : '0', 10)
      modalTotal.textContent = total
      modalStore.textContent = storeName
      var eta = document.getElementById('modalEta')
      if (eta) {
        eta.textContent = 'Tiempo estimado: ~' + formatTime(total * 1.0)
      }
      pricesConfirmModal.style.display = 'flex'
    })
  }

  if (btnConfirmStart) {
    btnConfirmStart.addEventListener('click', function () {
      pricesConfirmModal.style.display = 'none'
      showStep(3)
      var total = parseInt(modalTotal.textContent, 10)
      startBatch(total)
    })
  }

  if (btnCancelConfirm) {
    btnCancelConfirm.addEventListener('click', function () {
      pricesConfirmModal.style.display = 'none'
      fetch(cfg.urlRoot + '/connections/cancelPriceBatch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=' + encodeURIComponent(cfg.csrf)
      })
    })
  }

  function startBatch(total, resuming) {
    var startTime = Date.now()
    var errorCount = 0
    var maxErrors = 10
    var logCount = 0

    function processResponse(data) {
      if (data.error) {
        progressText.textContent = 'Error: ' + data.error
        progressText.style.color = '#e74c3c'
        if (data.done) {
          setTimeout(function () { window.location.reload() }, 2000)
        }
        return
      }

      var currentTotal = data.total || total
      var processed = data.processed
      var pct = Math.round((processed / currentTotal) * 100)
      progressFill.style.width = pct + '%'
      progressText.textContent = processed + ' de ' + currentTotal + ' (' + pct + '%)'
      progressText.style.color = ''
      if (progressCounter) progressCounter.innerHTML = '<span>' + processed + ' <span class="pw-pct">de ' + currentTotal + '</span></span><span class="pw-percent">' + pct + '%</span>'
      updateEta(processed, currentTotal, startTime)

      if (data.results) {
        if (data.restored) {
          processLogs.innerHTML = ''
          logCount = 0
        }
        data.results.forEach(function (r) {
          var entry = document.createElement('div')
          var isOk = r.status === 'success'
          entry.className = 'log-entry'
          entry.style.animation = 'pw-stat-in 0.2s ease both'
          entry.style.animationDelay = (logCount * 0.03) + 's'
          entry.innerHTML = '<span class="log-badge ' + (isOk ? 'ok' : 'err') + '">' + (isOk ? '✓' : '✗') + '</span> <span class="log-sku">' + esc(r.sku) + '</span> ' + esc(r.message)
          processLogs.appendChild(entry)
          logCount++
        })
        processLogs.scrollTop = processLogs.scrollHeight
      }

      if (!data.done) {
        errorCount = 0
        setTimeout(nextBatch, 100)
      } else {
        progressText.textContent = 'Completado.'
        setActiveStep(4)
        if (typeof showToast === 'function') {
          showToast('Ejecución finalizada correctamente', 'success', 5000)
        }
        setTimeout(function () { window.location.reload() }, 2000)
      }
    }

    function nextBatch() {
      var fd = new FormData()
      fd.append('csrf_token', cfg.csrf)

      fetch(cfg.urlRoot + '/connections/applyPriceBatch', {
        method: 'POST',
        body: fd,
      })
      .then(function (r) { return r.json() })
      .then(processResponse)
      .catch(function () {
        errorCount++
        if (errorCount >= maxErrors) {
          progressText.textContent = 'Se perdió la conexión. Recarga la página para reanudar automáticamente.'
          progressText.style.color = '#e74c3c'
        } else {
          progressText.textContent = 'Error de red, reintentando (' + errorCount + '/' + maxErrors + ')...'
          setTimeout(nextBatch, 2000)
        }
      })
    }

    var fd = new FormData()
    fd.append('csrf_token', cfg.csrf)

    fetch(cfg.urlRoot + '/connections/startPriceBatch', {
      method: 'POST',
      body: fd,
    })
    .then(function (r) { return r.json() })
    .then(function (data) {
      processResponse(data)
    })
    .catch(function () {
      errorCount++
      if (errorCount >= maxErrors) {
        progressText.textContent = 'Se perdió la conexión. Recarga la página para reanudar automáticamente.'
        progressText.style.color = '#e74c3c'
      } else {
        progressText.textContent = 'Error de red, reintentando (' + errorCount + '/' + maxErrors + ')...'
        setTimeout(nextBatch, 2000)
      }
    })
  }
})()

document.addEventListener('click', function (e) {
  var btn = e.target.closest('.rbs-copy')
  if (!btn) return
  e.stopPropagation()
  var bid = btn.getAttribute('data-batch')
  if (!bid) return
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(bid).then(function () {
      var orig = btn.innerHTML
      btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor"><path d="M13.854 3.646a.5.5.0 0 1 0 .708l-7 7a.5.5.0 0 1-.708 0l-3.5-3.5a.5.5.0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5.0 0 1 .708 0"/></svg>'
      setTimeout(function () { btn.innerHTML = orig }, 1500)
    }).catch(function () {})
  }
})

function initResultDonut(ok, err) {
  var el = document.getElementById('resultDonut')
  if (!el || typeof ApexCharts === 'undefined') return
  var total = ok + err
  var pct = total > 0 ? Math.round(ok / total * 100) : 0
  var ps = getComputedStyle(document.documentElement)
  var pc = ps.getPropertyValue('--text-primary').trim() || '#c9d1d9'
  var sc = ps.getPropertyValue('--text-secondary').trim() || '#8b949e'
  var mode = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark'

  new ApexCharts(el, {
    chart: {
      type: 'donut',
      height: 220,
      foreColor: pc
    },
    theme: { mode: mode },
    series: [ok, err],
    labels: ['Completados', 'Errores'],
    colors: ['#22c55e', '#ef4444'],
    stroke: { width: 0 },
    states: {
      hover: { filter: { type: 'none' } }
    },
    dataLabels: { enabled: false },
    plotOptions: {
      pie: {
        donut: {
          size: '74%',
          labels: {
            show: true,
            name: { show: false },
            value: {
              fontSize: '30px',
              fontFamily: '"Plus Jakarta Sans", sans-serif',
              fontWeight: 700,
              color: pct >= 90 ? '#22c55e' : (pct >= 70 ? '#f59e0b' : pc),
              formatter: function () { return pct + '%'; }
            },
            total: {
              show: true,
              label: 'éxito',
              fontSize: '14px',
              color: sc,
              formatter: function () { return pct + '%'; }
            }
          }
        }
      }
    },
    tooltip: {
      theme: mode,
      y: {
        formatter: function (val) { return val + ' items'; }
      }
    },
    legend: { show: false }
  }).render()
}

if (window.RESULT_DONUT_CONFIG) {
  document.addEventListener('DOMContentLoaded', function () {
    initResultDonut(window.RESULT_DONUT_CONFIG.totalOk, window.RESULT_DONUT_CONFIG.totalErr)
  })
}
