import os
import time
import subprocess
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

def run_test():
    print("🚀 Iniciando prueba de carga con Selenium WebDriver...")
    
    # Consultar estado actual de 2FA
    print("🔒 Consultando estado actual de 2FA para el usuario 'admin'...")
    original_2fa = "0"
    try:
        cmd_get = ["docker", "exec", "-i", "gestion_db", "mysql", "-ugestion_user", "-pgestion_pass", "gestion_db", "-N", "-B", "-e", "SELECT twofa_enabled FROM users WHERE username = 'admin';"]
        res = subprocess.run(cmd_get, capture_output=True, text=True)
        original_2fa = res.stdout.strip()
    except Exception as e:
        print(f"⚠️ Warning al obtener estado de 2FA: {e}")

    # Desactivar 2FA temporalmente para la prueba
    if original_2fa == "1":
        print("🔓 Desactivando temporalmente 2FA para realizar la prueba...")
        try:
            cmd_disable = ["docker", "exec", "-i", "gestion_db", "mysql", "-ugestion_user", "-pgestion_pass", "gestion_db", "-e", "UPDATE users SET twofa_enabled = 0 WHERE username = 'admin';"]
            subprocess.run(cmd_disable, capture_output=True)
        except Exception as e:
            print(f"⚠️ Error al desactivar 2FA: {e}")

    # Configurar opciones de Chrome
    options = webdriver.ChromeOptions()
    options.add_argument("--start-maximized")
    # Desactivar logs innecesarios de Chrome
    options.add_experimental_option('excludeSwitches', ['enable-logging'])
    
    driver = webdriver.Chrome(options=options)
    wait = WebDriverWait(driver, 15)
    
    try:
        # 1. Navegar al Login
        print("\n🔑 1. Accediendo a la página de inicio de sesión...")
        driver.get("http://localhost:8080/auth")
        
        # 2. Rellenar credenciales e iniciar sesión
        print("✍️  Ingresando credenciales de administrador...")
        username_input = wait.until(EC.presence_of_element_located((By.ID, "username")))
        password_input = driver.find_element(By.ID, "password")
        
        username_input.send_keys("admin")
        password_input.send_keys("password")
        
        submit_btn = driver.find_element(By.XPATH, "//button[@type='submit']")
        submit_btn.click()
        
        # Esperar a entrar al dashboard
        print("📥 Esperando redirección al Dashboard...")
        wait.until(EC.url_contains("/dashboard"))
        print("ok login")
        
        # 3. Ir a la sección de actualización masiva de Cencosud
        print("\n🔄 2. Navegando al módulo de actualización de Cencosud...")
        driver.get("http://localhost:8080/cencosud/updates")
        
        # Seleccionar tipo de actualización: Precios
        print("🎯 Configurando tipo de actualización a: PRECIOS")
        type_select = wait.until(EC.presence_of_element_located((By.ID, "csUpdType")))
        type_select.click()
        # Asegurar que está seleccionado 'price'
        driver.find_element(By.XPATH, "//select[@id='csUpdType']/option[@value='price']").click()
        
        # 4. Seleccionar el archivo CSV
        print("📁 Cargando archivo de pruebas: cencosud_test_prices.csv...")
        file_input = driver.find_element(By.ID, "csUpdFile")
        csv_path = os.path.abspath("muestras/cencosud_test_prices.csv")
        if not os.path.exists(csv_path):
            raise FileNotFoundError(f"No se encontró el archivo en la ruta: {csv_path}")
        file_input.send_keys(csv_path)
        
        # 5. Analizar el archivo
        print("🔍 Analizando estructura del archivo cargado...")
        prepare_btn = driver.find_element(By.ID, "csPrepareBtn")
        prepare_btn.click()
        
        # Esperar a que la vista previa esté visible
        print("⏳ Esperando que se genere la vista previa...")
        wait.until(EC.visibility_of_element_located((By.ID, "csPreviewCard")))
        
        preview_summary = driver.find_element(By.ID, "csPreviewSummary").text
        print(f"📊 Vista previa lista: {preview_summary}")
        
        # 6. Aplicar cambios
        print("\n🚀 3. Iniciando envío del lote a Cencosud (Modo Dry-Run)...")
        apply_btn = driver.find_element(By.ID, "csApplyBtn")
        apply_btn.click()
        
        # Esperar a que comience y termine el lote
        print("⏳ Procesando lote...")
        
        # Monitorear barra de progreso y texto en consola
        batch_text_el = wait.until(EC.presence_of_element_located((By.ID, "csBatchText")))
        
        last_text = ""
        while True:
            current_text = batch_text_el.text
            if current_text != last_text and current_text:
                print(f"   📈 Progreso: {current_text}")
                last_text = current_text
            
            if "COMPLETADO" in current_text:
                break
            time.sleep(0.5)
            
        print("✨ Procesamiento de lote finalizado con éxito.")
        
        # 7. Ir al Historial de actualizaciones
        print("\n📜 4. Navegando al Historial de actualizaciones...")
        driver.get("http://localhost:8080/cencosud/updateHistory")
        
        # Esperar que la tabla del historial cargue
        print("⏳ Cargando tabla de historial...")
        hist_body = wait.until(EC.presence_of_element_located((By.ID, "csHistBody")))
        
        # Buscar el botón 'Ver' del primer lote en la tabla
        print("🔍 Abriendo el detalle del lote recién creado...")
        first_ver_btn = wait.until(EC.element_to_be_clickable((By.XPATH, "//tbody[@id='csHistBody']/tr[1]//button[text()='Ver']")))
        first_ver_btn.click()
        
        # Esperar a que el modal de detalles se muestre
        wait.until(EC.visibility_of_element_located((By.ID, "csBatchModal")))
        print("📋 Modal de detalles abierto correctamente.")
        
        print("\n🎉 ¡Prueba de carga completada exitosamente!")
        print("💻 Deja esta ventana abierta para mostrar el resultado a tu jefe.")
        
        # Mantener el navegador abierto
        input("\n⚠️ Presiona [Enter] en esta consola cuando desees cerrar el navegador y terminar la prueba...")
        
    except Exception as e:
        import traceback
        print("\n❌ Ocurrió un error durante la prueba:")
        traceback.print_exc()
        try:
            print(f"📍 URL actual: {driver.current_url}")
            screenshot_path = os.path.abspath("selenium_error.png")
            driver.save_screenshot(screenshot_path)
            print(f"📸 Captura de pantalla guardada en: {screenshot_path}")
        except Exception as se:
            print(f"⚠️ No se pudo guardar la captura de pantalla: {se}")
        input("\n⚠️ Presiona [Enter] para cerrar el navegador...")
        
    finally:
        if 'original_2fa' in locals() and original_2fa == "1":
            print("\n🔒 Restaurando estado original de 2FA...")
            try:
                cmd_enable = ["docker", "exec", "-i", "gestion_db", "mysql", "-ugestion_user", "-pgestion_pass", "gestion_db", "-e", "UPDATE users SET twofa_enabled = 1 WHERE username = 'admin';"]
                subprocess.run(cmd_enable, capture_output=True)
            except Exception as e:
                print(f"⚠️ Error al restaurar 2FA: {e}")
        print("👋 Cerrando navegador de Selenium...")
        try:
            driver.quit()
        except NameError:
            pass

if __name__ == "__main__":
    run_test()
