(function () {
  'use strict'

  /* ═══════════ Dropzone Keyboard Support ═══════════ */
  window.wpDropzoneKeydown = function (event, inputId) {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      var input = document.getElementById(inputId)
      if (input) input.click()
    }
  }

  /* ═══════════ Drag & Drop File Zones ═══════════ */
  function setupDropzone(dropzoneId, inputId, displayId) {
    var dropzone = document.getElementById(dropzoneId)
    var input = document.getElementById(inputId)
    var display = document.getElementById(displayId)
    if (!dropzone || !input) return

    var showFileName = function (file) {
      if (display && file) {
        display.textContent = file.name
        display.classList.add('is-visible')
      }
    }

    dropzone.addEventListener('click', function () {
      input.click()
    })

    input.addEventListener('change', function () {
      if (input.files && input.files[0]) {
        showFileName(input.files[0])
      }
    })

    ;['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (ev) {
      dropzone.addEventListener(ev, function (e) {
        e.preventDefault()
        e.stopPropagation()
      })
    })

    ;['dragenter', 'dragover'].forEach(function (ev) {
      dropzone.addEventListener(ev, function () {
        dropzone.classList.add('is-dragover')
        dropzone.classList.add('dragover')
      })
    })

    ;['dragleave', 'drop'].forEach(function (ev) {
      dropzone.addEventListener(ev, function () {
        dropzone.classList.remove('is-dragover')
        dropzone.classList.remove('dragover')
      })
    })

    dropzone.addEventListener('drop', function (e) {
      var files = e.dataTransfer.files
      if (files.length) {
        input.files = files
        showFileName(files[0])
        var changeEvent = new Event('change', { bubbles: true })
        input.dispatchEvent(changeEvent)
      }
    })
  }

  setupDropzone('wpDataDropzone', 'dataFileInput', 'wpDataFileName')
  setupDropzone('wpTemplateDropzone', 'templateFileInput', 'wpTemplateFileName')
  setupDropzone('wpReviewDropzone', 'reviewFileInput', 'wpReviewFileName')

  /* ═══════════ Confirm Dialogs ═══════════ */
  document.addEventListener('submit', function (e) {
    var form = e.target
    if (form.hasAttribute('data-confirm')) {
      if (!confirm(form.getAttribute('data-confirm'))) {
        e.preventDefault()
      }
    }
  })

  /* ═══════════ URL State Sync ═══════════ */
  var loadUrlState = function () {
    var params = new URLSearchParams(window.location.search)
    var product = params.get('product')

    if (product) {
      setTimeout(function () {
        var productRow = document.getElementById('product-' + product)
        if (productRow) {
          productRow.scrollIntoView({ behavior: 'smooth', block: 'center' })
          productRow.style.transition = 'background-color 0.3s ease'
          productRow.style.backgroundColor = 'rgba(var(--wp-teal-rgb), 0.15)'
          setTimeout(function () {
            productRow.style.backgroundColor = ''
          }, 2000)
        }
      }, 500)
    }
  }


  /* ═══════════ Table Virtualization for Large Lists ═══════════ */
  var setupTableVirtualization = function () {
    var tableWrap = document.querySelector('.wp-table-wrap')
    var table = tableWrap?.querySelector('.wp-table')
    if (!tableWrap || !table) return

    var rows = Array.from(table.querySelectorAll('tbody tr'))
    var ROW_HEIGHT = 40
    var VISIBLE_BUFFER = 5
    var containerHeight = tableWrap.clientHeight

    if (rows.length <= 20 || containerHeight <= 0) return

    table.classList.add('is-virtualized')
    tableWrap.classList.add('is-virtualized')

    var visibleRows = Math.ceil(containerHeight / ROW_HEIGHT) + VISIBLE_BUFFER * 2

    var renderVisibleRows = function () {
      var scrollTop = tableWrap.scrollTop
      var startIndex = Math.max(0, Math.floor(scrollTop / ROW_HEIGHT) - VISIBLE_BUFFER)
      var endIndex = Math.min(rows.length, startIndex + visibleRows)
      rows.forEach(function (row, index) {
        row.style.display = (index >= startIndex && index < endIndex) ? '' : 'none'
      })
    }

    window._wpVScroll = function () { window.requestAnimationFrame(renderVisibleRows) }
    tableWrap.addEventListener('scroll', window._wpVScroll)
    renderVisibleRows()
  }

  function clearTableVirtualization() {
    var tableWrap = document.querySelector('.wp-table-wrap')
    var table = tableWrap?.querySelector('.wp-table')
    if (!tableWrap || !table) return
    tableWrap.classList.remove('is-virtualized')
    table.classList.remove('is-virtualized')
    table.querySelectorAll('tbody tr').forEach(function (row) { row.style.display = '' })
    if (window._wpVScroll) { tableWrap.removeEventListener('scroll', window._wpVScroll) }
  }

  /* ═══════════ Table Search & Filter ═══════════ */
  var setupTableSearch = function () {
    var searchInput = document.getElementById('wpTableSearch')
    var clearBtn = document.getElementById('wpSearchClear')
    var filterErrors = document.getElementById('wpFilterErrors')
    
    if (!searchInput) return

    var table = searchInput.closest('.wp-section')?.querySelector('.wp-table')
    if (!table) return

    var filterRows = function () {
      var query = searchInput.value.trim().toLowerCase()
      var showErrorsOnly = filterErrors?.getAttribute('aria-pressed') === 'true'

      table.querySelectorAll('tbody tr').forEach(function (row) {
        var sku = row.querySelector('td:nth-child(2)')?.textContent?.toLowerCase() || ''
        var nombre = row.querySelector('td:nth-child(3)')?.textContent?.toLowerCase() || ''
        var hasError = row.classList.contains('has-errors')
        var hasWarning = row.classList.contains('has-issues')

        var matchesSearch = !query || sku.includes(query) || nombre.includes(query)
        var matchesFilter = !showErrorsOnly || hasError || hasWarning

        if (matchesSearch && matchesFilter) {
          row.style.display = ''
        } else {
          row.style.display = 'none'
        }
      })

      if (clearBtn) {
        clearBtn.style.display = query ? 'flex' : 'none'
      }
    }

    searchInput.addEventListener('input', filterRows)
    
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        searchInput.value = ''
        searchInput.focus()
        filterRows()
      })
    }

    if (filterErrors) {
      filterErrors.addEventListener('click', function () {
        var isPressed = filterErrors.getAttribute('aria-pressed') === 'true'
        filterErrors.setAttribute('aria-pressed', !isPressed)
        filterRows()
      })
    }
  }

  /* ═══════════ Copy Product Link ═══════════ */
  window.wpCopyProductLink = function (sku) {
    var url = new URL(window.location.href)
    url.searchParams.set('product', sku)
    
    navigator.clipboard.writeText(url.toString()).then(function () {
      showToast('Enlace copiado', 'success', 2000)
      wpAnnounce('Enlace copiado al portapapeles')
    }).catch(function () {
      showToast('No se pudo copiar el enlace', 'error', 3000)
      wpAnnounce('No se pudo copiar el enlace')
    })
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadUrlState()
    setupTableVirtualization()
    setupTableSearch()

    /* ═══════════ Show Skeleton on Page Load (if needed) ═══════════ */
    var skeleton = document.getElementById('wpTableSkeleton')
    var table = document.querySelector('.wp-table')
    if (skeleton && table) {
      skeleton.style.display = 'none'
    }
  })

  /* ═══════════ Show Skeleton Before Form Submit ═══════════ */
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var action = form.getAttribute('action')
      if (action && (action.includes('uploadData') || action.includes('uploadTemplate') || action.includes('uploadForReview'))) {
        var skeleton = document.getElementById('wpTableSkeleton')
        if (skeleton) {
          skeleton.style.display = 'block'
          setTimeout(function () {
            skeleton.style.display = 'none'
          }, 5000)
        }
      }
    })
  })

  /* ═══════════ Form Validation ═══════════ */
  var productForm = document.getElementById('productForm')
  if (productForm) {
    var validateField = function (input) {
      var errorDiv = input.parentElement.querySelector('.wp-form-error')
      var value = input.value.trim()
      var error = null

      if (input.hasAttribute('required') && !value) {
        error = 'Este campo es requerido'
      } else if (input.id === 'modalSku' && value) {
        if (!/^[A-Z0-9\-/]+$/.test(value)) {
          error = 'Solo letras, números, guiones y barras'
        } else if (value.length < 2) {
          error = 'Mínimo 2 caracteres'
        }
      } else if (input.id === 'modalNombre' && value) {
        if (value.length < 3) {
          error = 'Mínimo 3 caracteres'
        } else if (value.length > 200) {
          error = 'Máximo 200 caracteres'
        }
      } else if (input.id === 'modalStock') {
        var num = parseInt(value, 10)
        if (isNaN(num) || num < 0) {
          error = 'Debe ser 0 o mayor'
        } else if (num > 999999) {
          error = 'Máximo 999,999'
        }
      }

      if (error) {
        input.classList.add('is-invalid')
        input.setAttribute('aria-invalid', 'true')
        if (errorDiv) {
          errorDiv.textContent = error
          errorDiv.style.display = 'block'
        }
        return false
      } else {
        input.classList.remove('is-invalid')
        input.setAttribute('aria-invalid', 'false')
        if (errorDiv) {
          errorDiv.textContent = ''
          errorDiv.style.display = 'none'
        }
        return true
      }
    }

    var validateForm = function () {
      var isValid = true
      productForm.querySelectorAll('input, textarea').forEach(function (field) {
        if (!validateField(field)) isValid = false
      })
      return isValid
    }

    productForm.addEventListener('submit', function (e) {
      if (!validateForm()) {
        e.preventDefault()
        var firstInvalid = productForm.querySelector('.is-invalid')
        if (firstInvalid) firstInvalid.focus()
      }
    })

    productForm.querySelectorAll('input, textarea').forEach(function (field) {
      field.addEventListener('blur', function () {
        validateField(field)
      })

      field.addEventListener('input', function () {
        if (field.classList.contains('is-invalid')) {
          validateField(field)
        }
      })
    })
  }

  /* ═══════════ Unsaved Changes Warning ═══════════ */
  var hasUnsavedChanges = false

  var checkUnsavedChanges = function () {
    var dataBatch = document.querySelector('input[name="data_file"]')
    var templateFile = document.querySelector('input[name="template_file"]')
    var reviewFile = document.querySelector('input[name="review_file"]')
    
    hasUnsavedChanges = (dataBatch && dataBatch.files.length > 0) ||
                        (templateFile && templateFile.files.length > 0) ||
                        (reviewFile && reviewFile.files.length > 0)
  }

  document.querySelectorAll('input[type="file"]').forEach(function (input) {
    input.addEventListener('change', checkUnsavedChanges)
  })

  window.addEventListener('beforeunload', function (e) {
    if (hasUnsavedChanges) {
      e.preventDefault()
      e.returnValue = ''
      return ''
    }
  })

  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function () {
      hasUnsavedChanges = false
    })
  })

  /* ═══════════ Button Loading State ═══════════ */
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var submitBtn = form.querySelector('button[type="submit"]')
      if (submitBtn && !submitBtn.classList.contains('wp-btn-ghost')) {
        var originalText = submitBtn.innerHTML
        submitBtn.setAttribute('data-original', originalText)
        submitBtn.disabled = true
        submitBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:wpSpin 1s linear infinite"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 10 10" opacity="0.5"/></svg> <span>Procesando…</span>'
        
        setTimeout(function () {
          submitBtn.disabled = false
          submitBtn.innerHTML = submitBtn.getAttribute('data-original') || originalText
        }, 30000)
      }
    })
  })

  /* ═══════════ Step Navigation ═══════════ */
  var wpSteps = [1, 2, 3, 4]
  var wpCurrentStep = 1

  window.wpShowStep = function (num) {
    if (wpSteps.indexOf(num) === -1) return

    document.querySelectorAll('.step-panel').forEach(function (el) {
      el.classList.toggle('is-active', parseInt(el.getAttribute('data-step')) === num)
    })

    wpCurrentStep = num
    updateStepper(num)

    var panel = document.querySelector('.step-panel.is-active')
    if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' })

    // Re-init table virtualization when review step becomes visible
    if (num === 2) {
      clearTableVirtualization()
      setTimeout(function () { setupTableVirtualization() }, 100)
    }

    wpAnnounce('Paso ' + num + ' activado')
  }

  function clearTableVirtualization() {
    var tableWrap = document.querySelector('.wp-table-wrap')
    var table = tableWrap?.querySelector('.wp-table')
    if (!tableWrap || !table) return
    tableWrap.classList.remove('is-virtualized')
    table.classList.remove('is-virtualized')
    table.querySelectorAll('tbody tr').forEach(function (row) { row.style.display = '' })
    tableWrap.removeEventListener('scroll', window._wpVScroll)
  }

  function updateStepper(num) {
    var stepper = document.getElementById('wpStepper')
    if (!stepper) return

    stepper.querySelectorAll('.pw-step').forEach(function (el) {
      var step = parseInt(el.getAttribute('data-step'))
      el.classList.toggle('completed', step < num)
      el.classList.toggle('active', step === num)
      var circle = el.querySelector('.pw-step-circle')
      if (circle) circle.textContent = step < num ? '✓' : step
    })

    stepper.querySelectorAll('.pw-step-connector').forEach(function (el) {
      var conn = parseInt(el.getAttribute('data-connector'))
      el.classList.toggle('done', conn < num)
    })
  }

  /* ═══════════ Init: Show correct step on load ═══════════ */
  document.addEventListener('DOMContentLoaded', function () {
    var app = document.getElementById('wpApp')
    var initial = app ? parseInt(app.getAttribute('data-initial-step')) || 1 : 1
    wpShowStep(initial)
  })

  /* ═══════════ Live Region Updates ═══════════ */
  window.wpAnnounce = function (message) {
    var liveRegion = document.getElementById('wpLiveRegion')
    if (liveRegion) {
      liveRegion.textContent = message
      setTimeout(function () {
        liveRegion.textContent = ''
      }, 1000)
    }
  }
})()
