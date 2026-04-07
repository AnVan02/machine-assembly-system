document.addEventListener('DOMContentLoaded', async () => {
    const inputs = document.querySelectorAll('.scan-input');
    const confirmBtn = document.getElementById('btnConfirm');
    const scanFileInput = document.getElementById('scan-file-input');
    let currentScanningInput = null;
    let currentScanningIcon = null;

    // Tự động focus vào ô đầu tiên
    if (inputs.length > 0) {
        inputs[0].focus();
    }

    // Config scanner API - Sử dụng proxy để tránh lỗi CORS và Cookie
    // Sử dụng URL tuyệt đối dựa trên trang hiện tại để đảm bảo fetch() hoạt động ổn định
    const PROXY_FILENAME = 'scanner-proxy.php';
    const isLoginPage = window.location.pathname.includes('/login/');
    const PROXY_REL_PATH = isLoginPage ? '../' + PROXY_FILENAME : PROXY_FILENAME;
    const PROXY_URL = new URL(PROXY_REL_PATH, window.location.href).href;
    const SCANNER_API_BASE = PROXY_URL + '?path=';

    // --- LocalStorage Persistence Support ---
    const STORAGE_KEY = (typeof currentOrderId !== 'undefined' && typeof currentConfigName !== 'undefined')
        ? `scan_data_${currentOrderId}_${currentConfigName.replace(/\s+/g, '_')}`
        : 'scan_data_common';

    function saveToLocalStorage() {
        const data = Array.from(inputs).map(input => ({
            id_ct: input.getAttribute('data-id-ct'),
            value: input.value.trim(),
            status: input.classList.contains('is-valid') ? 'valid' : (input.classList.contains('is-invalid') ? 'invalid' : ''),
            msg: input.closest('.comp-input-side')?.querySelector('.scan-error-msg')?.textContent || '',
            available: input.getAttribute('data-available')
        }));
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        console.log('Saved to localStorage:', STORAGE_KEY);
    }

    function loadFromLocalStorage() {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (!saved) return;

        try {
            const data = JSON.parse(saved);
            inputs.forEach((input, idx) => {
                const item = data[idx];
                const dbValue = input.value;
                if (item && item.value && (item.value !== dbValue || !dbValue)) {
                    input.value = item.value;
                    if (item.id_ct) input.setAttribute('data-id-ct', item.id_ct);
                    if (item.available) input.setAttribute('data-available', item.available);

                    if (item.status === 'valid') {
                        setInputStatus(input, 'success', item.msg);
                    } else if (item.status === 'invalid') {
                        setInputStatus(input, 'error', item.msg);
                    }
                }
            });
            console.log('Restored from localStorage:', STORAGE_KEY);
        } catch (e) {
            console.error('Lỗi khi nạp dữ liệu từ localStorage', e);
        }
    }

    function clearLocalStorage() {
        localStorage.removeItem(STORAGE_KEY);
        console.log('Cleared localStorage:', STORAGE_KEY);
    }

    // Scanner UI Elements
    const fileInput = document.getElementById("cameraInput");
    const previewImg = document.getElementById("preview-img");
    const placehoder = document.getElementById("placeholder")
    const statusBar = document.getElementById('scanner-status-bar');
    const statusText = statusBar?.querySelector('.status-text');
    const btnScannerLogin = document.getElementById('btn-scanner-login');
    const btnScannerLogout = document.getElementById('btn-scanner-logout');
    const loginModal = document.getElementById('scanner-login-modal');
    const btnCloseModal = document.getElementById('btn-close-scanner-modal');
    const btnDoLogin = document.getElementById('btn-do-scanner-login');
    const inputUser = document.getElementById('l-username');
    const inputPass = document.getElementById('l-password');

    const btnScannerRegister = document.getElementById('btn-scanner-register');
    const btnScannerLogoutAll = document.getElementById('btn-scanner-logout-all');
    const registerModal = document.getElementById('scanner-register-modal');
    const btnCloseRegisterModal = document.getElementById('btn-close-register-modal');
    const btnDoRegister = document.getElementById('btn-do-scanner-register');
    const inputRegUser = document.getElementById('r-username');
    const inputRegEmail = document.getElementById('r-email');
    const inputRegPass = document.getElementById('r-password');

    function showMsg(id, text, type) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = text;
        el.style.display = 'block';
        el.style.background = type === 'success' ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)';
        el.style.color = type === 'success' ? '#10b981' : '#ef4444';
    }

    // --- Scanner Auth Functions ---
    async function checkScannerAuth(isRetry = false) {
        try {
            const url = `${SCANNER_API_BASE}/auth/me`;
            console.log('Checking scanner auth:', url);
            const res = await fetch(url, { credentials: 'include' });

            if (res.ok) {
                const user = await res.json();
                updateScannerUI(true, user.username);
            } else if (res.status === 401 && !isRetry) {
                console.warn('Scanner 401 - Attempting refresh...');
                const refreshRes = await fetch(`${SCANNER_API_BASE}/auth/refresh`, {
                    method: 'POST',
                    credentials: 'include'
                });
                if (refreshRes.ok) {
                    return checkScannerAuth(true);
                } else {
                    updateScannerUI(false);
                }
            } else {
                console.log('Scanner auth failed:', res.status);
                updateScannerUI(false);
            }
        } catch (err) {
            console.error('Lỗi kết nối máy chủ quét:', err);
            if (statusText) statusText.textContent = `Lỗi kết nối: ${err.message || 'Không xác định'}`;
            statusBar?.classList.add('unauthenticated');
        }
    }

    function updateScannerUI(isAuthenticated, username = '') {
        if (!statusBar) return;

        if (isAuthenticated) {
            statusBar.classList.remove('unauthenticated');
            statusBar.classList.add('authenticated');
            if (statusText) statusText.textContent = `Đã kết nối: ${username}`;
            if (btnScannerLogin) btnScannerLogin.style.display = 'none';
            if (btnScannerRegister) btnScannerRegister.style.display = 'none';
            if (btnScannerLogout) btnScannerLogout.style.display = 'block';
            if (btnScannerLogoutAll) btnScannerLogoutAll.style.display = 'block';
        } else {
            statusBar.classList.remove('authenticated');
            statusBar.classList.add('unauthenticated');
            if (statusText) statusText.textContent = 'Chưa kết nối dịch vụ Quét';
            if (btnScannerLogin) btnScannerLogin.style.display = 'block';
            if (btnScannerRegister) btnScannerRegister.style.display = 'block';
            if (btnScannerLogout) btnScannerLogout.style.display = 'none';
            if (btnScannerLogoutAll) btnScannerLogoutAll.style.display = 'none';
        }
    }

    // --- Scanner UI Events ---
    btnScannerLogin?.addEventListener('click', () => {
        if (loginModal) loginModal.style.display = 'flex';
    });

    btnScannerRegister?.addEventListener('click', () => {
        if (registerModal) registerModal.style.display = 'flex';
    });

    btnCloseModal?.addEventListener('click', () => {
        if (loginModal) loginModal.style.display = 'none';
    });

    btnCloseRegisterModal?.addEventListener('click', () => {
        if (registerModal) registerModal.style.display = 'none';
    });

    btnScannerLogout?.addEventListener('click', async () => {
        if (!confirm('Bạn có muốn đăng xuất khỏi dịch vụ quét?')) return;
        try {
            await fetch(`${SCANNER_API_BASE}/auth/logout`, { method: 'POST', credentials: 'include' });
            updateScannerUI(false);
        } catch (err) {
            alert('Lỗi khi đăng xuất');
        }
    });

    btnScannerLogoutAll?.addEventListener('click', async () => {
        if (!confirm('Bạn có CỰC KỲ CHẮC CHẮN muốn đăng xuất khỏi TẤT CẢ thiết bị không? Hành động này sẽ vô hiệu hoá các thiết bị khác đang kết nối.')) return;
        try {
            const res = await fetch(`${SCANNER_API_BASE}/auth/sessions`, { method: 'DELETE', credentials: 'include' });
            if (res.ok) {
                alert('Đã đăng xuất khỏi tất cả các thiết bị thành công!');
                updateScannerUI(false);
            } else {
                alert('Lỗi khi đăng xuất tất cả thiết bị');
            }
        } catch (err) {
            alert('Lỗi kết nối khi đăng xuất');
        }
    });
    btnDoRegister?.addEventListener('click', async () => {
        const username = inputRegUser?.value.trim();
        const email = inputRegEmail?.value.trim();
        const password = inputRegPass?.value.trim();

        if (!username || !email || !password) {
            showMsg('registerMsg', 'Vui lòng nhập đầy đủ Username, Email và Mật khẩu', 'error');
            return;
        }

        btnDoRegister.disabled = true;
        btnDoRegister.textContent = 'Đang xử lý...';

        try {
            const res = await fetch(`${SCANNER_API_BASE}/auth/register`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, email, password })
            });

            if (res.ok) {
                showMsg('registerMsg', '✓ Tạo tài khoản thành công! Hãy đăng nhập.', 'success');
                setTimeout(() => {
                    if (registerModal) registerModal.style.display = 'none';
                    if (loginModal) {
                        loginModal.style.display = 'flex';
                        if (inputUser) inputUser.value = username;
                        if (inputPass) inputPass.value = password;
                    }
                }, 1500);
            } else {
                const err = await res.json();
                showMsg('registerMsg', err.detail || 'Đăng ký thất bại', 'error');
            }
        } catch (err) {
            showMsg('registerMsg', 'Không thể kết nối tới máy chủ quét', 'error');
        } finally {
            if (btnDoRegister) {
                btnDoRegister.disabled = false;
                btnDoRegister.textContent = 'Tạo tài khoản';
            }
        }
    });

    btnDoLogin?.addEventListener('click', async () => {
        const username = inputUser?.value.trim();
        const password = inputPass?.value.trim();

        if (!username || !password) {
            showMsg('loginMsg', 'Vui lòng nhập đầy đủ tài khoản và mật khẩu', 'error');
            return;
        }

        btnDoLogin.disabled = true;
        btnDoLogin.textContent = 'Đang xử lý...';

        try {
            const res = await fetch(`${SCANNER_API_BASE}/auth/login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password }),
                credentials: 'include'
            });

            if (res.ok) {
                showMsg('loginMsg', '✓ Đăng nhập thành công!', 'success');
                setTimeout(async () => {
                    if (loginModal) loginModal.style.display = 'none';
                    await checkScannerAuth();
                }, 800);
            } else {
                const err = await res.json();
                showMsg('loginMsg', err.detail || 'Sai tài khoản hoặc mật khẩu', 'error');
            }
        } catch (err) {
            showMsg('loginMsg', 'Không thể kết nối tới máy chủ quét', 'error');
        } finally {
            if (btnDoLogin) {
                btnDoLogin.disabled = false;
                btnDoLogin.textContent = 'Đăng nhập';
            }
        }
    });

    // Check auth on load
    checkScannerAuth();

    // Nạp dữ liệu đã lưu từ session trước (nếu có)
    loadFromLocalStorage();

    // Scanner UI Modal Elements (Premium Modal)
    const scannerUiModal = document.getElementById('scanner-ui-modal');
    const btnCloseScannerUi = document.querySelector('.btn-close-scanner');
    const modalPreviewArea = document.getElementById('modalPreviewArea');
    const modalPlaceholder = document.getElementById('modalPlaceholder');
    const modalPreviewImg = document.getElementById('modal-preview-img');
    const btnModalCapture = document.getElementById('btnModalCapture');
    const btnModalScan = document.getElementById('btnModalScan');
    const modalStatus = document.getElementById('modalStatus');
    const modalLoading = document.getElementById('modalLoading');
    const modalResultArea = document.getElementById('modalResultArea');
    const modalHiddenInput = document.createElement('input');
    modalHiddenInput.type = 'file';
    modalHiddenInput.accept = 'image/*';
    modalHiddenInput.capture = 'environment';

    // Handle scan icon click to OPEN MODAL
    document.querySelectorAll('.scan-icon-inside').forEach(icon => {
        icon.style.cursor = 'pointer';
        icon.title = 'Quét mã từ ảnh/camera';
        icon.addEventListener('click', () => {
            currentScanningInput = icon.closest('.comp-input-side').querySelector('.scan-input');
            currentScanningIcon = icon;

            if (scannerUiModal) {
                scannerUiModal.style.display = 'flex';
                resetModalUI();
            } else {
                scanFileInput.click();
            }
        });
    });

    function resetModalUI() {
        modalPreviewImg.style.display = 'none';
        modalPreviewImg.src = '';
        modalPlaceholder.style.display = 'flex';
        modalStatus.textContent = 'Chưa chọn ảnh nào';
        modalStatus.style.display = 'block';
        modalLoading.style.display = 'none';
        btnModalScan.disabled = true;
        modalResultArea.style.display = 'none';
        modalResultArea.innerHTML = '';
    }

    document.querySelectorAll('.btn-close-scanner').forEach(btn => {
        btn.addEventListener('click', () => {
            if (scannerUiModal) scannerUiModal.style.display = 'none';
        });
    });


    // Close on click outside container
    scannerUiModal?.addEventListener('click', (e) => {
        if (e.target === scannerUiModal) {
            scannerUiModal.style.display = 'none';
        }
    });

    btnModalCapture?.addEventListener('click', () => {
        modalHiddenInput.click();
    });

    modalPreviewArea?.addEventListener('click', () => {
        modalHiddenInput.click();
    });

    modalHiddenInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (event) => {
            modalPreviewImg.src = event.target.result;
            modalPreviewImg.style.display = 'block';
            modalPlaceholder.style.display = 'none';
            btnModalScan.disabled = false;
            modalStatus.textContent = `✓ Đã chọn: ${file.name}`;
            modalResultArea.style.display = 'none';
        };
        reader.readAsDataURL(file);
    });

    btnModalScan?.addEventListener('click', async () => {
        const file = modalHiddenInput.files[0];
        if (!file) return;

        btnModalScan.disabled = true;
        modalStatus.style.display = 'none';
        modalLoading.style.display = 'flex';
        modalResultArea.style.display = 'none';

        const formData = new FormData();
        formData.append('file', file);

        try {
            const res = await fetch(`${SCANNER_API_BASE}/scan`, {
                method: 'POST',
                credentials: 'include',
                body: formData
            });

            if (res.status === 401) {
                console.warn('Session 401 - Đang thử làm mới token...');
                const refreshRes = await fetch(`${SCANNER_API_BASE}/auth/refresh`, {
                    method: 'POST',
                    credentials: 'include'
                });

                if (refreshRes.ok) {
                    console.log('Làm mới token thành công, thử lại request quét...');
                    return btnModalScan.click();
                }

                modalLoading.style.display = 'none';
                btnModalScan.disabled = false;
                console.error('Refresh token thất bại hoặc không tồn tại.');
                alert('Phiên làm việc hết hạn. Hãy nhấn nút "Kết nối" ở thanh trạng thái để đăng nhập lại.');
                updateScannerUI(false);
                return;
            }

            modalLoading.style.display = 'none';
            btnModalScan.disabled = false;
            const result = await res.json();

            if (result.success && result.results && result.results.length > 0) {
                showModalResults(result.results);
            } else {
                modalStatus.style.display = 'block';
                modalStatus.textContent = '❌ Không tìm thấy mã nào';
                modalStatus.style.color = '#ef4444';
            }
        } catch (err) {
            console.error(err);
            modalLoading.style.display = 'none';
            modalStatus.style.display = 'block';
            modalStatus.textContent = '❌ Lỗi kết nối server';
        } finally {
            btnModalScan.disabled = false;
        }
    });
    // Hàm hiển thị kết quả quét trong modal và tự động kiểm tra serial
    async function showModalResults(results) {
        modalResultArea.innerHTML = '';
        modalResultArea.style.display = 'block';

        // TỰ ĐỘNG KIỂM TRA: Tìm xem trong danh sách quét được có cái nào là số serial hợp lệ không
        const orderIdVal = (typeof currentOrderId !== 'undefined') ? currentOrderId : 0;
        const cfgNameVal = (typeof currentConfigName !== 'undefined') ? currentConfigName : '';

        // Hiển thị trạng thái đang kiểm tra tự động
        modalStatus.style.display = 'block';
        modalStatus.textContent = 'Đang kiểm tra số serial...';
        modalStatus.style.color = '#1152D41A';
        const checkPromises = results.map(res => {
            return fetch('kiemtra.php?ajax=1', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_donhang: orderIdVal,
                    so_serial: res.data.trim(),
                    ten_linhkien: currentScanningInput.getAttribute('data-name') || '',
                    loai_linhkien: currentScanningInput.getAttribute('data-loai') || '',
                    config_name: cfgNameVal
                })
            }).then(r => r.json()).then(data => ({ data: res.data, res: data }));
        });

        const checkedResults = await Promise.all(checkPromises);
        const validMatch = checkedResults.find(r => r.res.status === 'match');

        if (validMatch) {
            // Nếu tìm thấy cái khớp hoàn hảo, chọn luôn và đóng modal
            if (currentScanningInput) {
                currentScanningInput.value = validMatch.data;

                // Cập nhật giao diện input ngay lập tức
                setInputStatus(currentScanningInput, 'success', validMatch.res.message);
                if (validMatch.res.id_ct) currentScanningInput.setAttribute('data-id-ct', validMatch.res.id_ct);
                if (validMatch.res.available_count !== undefined) currentScanningInput.setAttribute('data-available', validMatch.res.available_count);

                saveToLocalStorage(); // Lưu ngay khi có kết quả hợp lệ

                if (scannerUiModal) scannerUiModal.style.display = 'none';

                // Focus next
                const allInputs = Array.from(inputs);
                const idx = allInputs.indexOf(currentScanningInput);
                if (idx < allInputs.length - 1) allInputs[idx + 1].focus();
                return; // Xong!
            }
        }
        // Nếu không có cái nào tự động khớp, hiện danh sách để người dùng chọn (như cũ) hoặc báo lỗi
        modalStatus.textContent = ' Không tìm thấy serial hợp lệ trong kết quả quét. Vui lòng thử với ảnh khác ';
        modalStatus.style.color = '#ef4444';
        results.forEach(res => {
            const item = document.createElement('div');
            item.className = 'scan-result-item';
            item.innerHTML = `
                <div class="res-info">
                    <span class="res-type">${res.type}</span>
                    <span class="res-data">${res.data}</span>
                </div>
                <button type="button" class="btn-use-result">CHỌN</button>
            `;

            item.querySelector('.btn-use-result').addEventListener('click', async () => {
                if (currentScanningInput) {
                    currentScanningInput.value = res.data;
                    scannerUiModal.style.display = 'none';
                    await validateSerial(currentScanningInput);
                    saveToLocalStorage(); // Lưu sau khi validate xong

                    // Focus next
                    const allInputs = Array.from(inputs);
                    const idx = allInputs.indexOf(currentScanningInput);
                    if (idx < allInputs.length - 1) allInputs[idx + 1].focus();
                }
            });

            modalResultArea.appendChild(item);
        });
    }
    // Handle file selection
    scanFileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file || !currentScanningInput) return;

        await handleFileScan(file, currentScanningInput, currentScanningIcon);

        // Reset file input
        scanFileInput.value = '';
    });

    async function handleFileScan(file, targetInput, icon) {
        const originalIconHTML = icon.innerHTML;
        icon.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        icon.style.pointerEvents = 'none';

        const formData = new FormData();
        formData.append('file', file);

        try {
            const res = await fetch(`${SCANNER_API_BASE}/scan`, {
                method: 'POST',
                credentials: 'include', //kiểm tra từ API_DOCUMENT.
                body: formData
            });

            if (res.status === 401) {
                console.warn('handleFileScan: 401 - Đang thử làm mới token...');
                const refreshRes = await fetch(`${SCANNER_API_BASE}/auth/refresh`, {
                    method: 'POST',
                    credentials: 'include'
                });

                if (refreshRes.ok) {
                    console.log('Làm mới token thành công, thử lại handleFileScan...');
                    return handleFileScan(file, targetInput, icon);
                } else {
                    console.error('handleFileScan: Refresh token thất bại.');
                    alert('Phiên đăng nhập quét mã đã hết hạn. Vui lòng kết nối lại ở thanh trạng thái.');
                    updateScannerUI(false);
                    return;
                }
            }

            const result = await res.json();
            if (result.success && result.results && result.results.length > 0) {
                // TỰ ĐỘNG LỌC: Tìm serial đúng trong danh sách trả về
                const orderIdVal = (typeof currentOrderId !== 'undefined') ? currentOrderId : 0;
                const cfgNameVal = (typeof currentConfigName !== 'undefined') ? currentConfigName : '';

                const checkPromises = result.results.map(rs => {
                    return fetch('kiemtra.php?ajax=1', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            id_donhang: orderIdVal,
                            so_serial: rs.data.trim(),
                            ten_linhkien: targetInput.getAttribute('data-name') || '',
                            loai_linhkien: targetInput.getAttribute('data-loai') || '',
                            config_name: cfgNameVal
                        })
                    }).then(r => r.json()).then(d => ({ data: rs.data, res: d }));
                });

                const checkedResults = await Promise.all(checkPromises);
                const validMatch = checkedResults.find(r => r.res.status === 'match');

                let scannedData = '';
                if (validMatch) {
                    scannedData = validMatch.data;
                    targetInput.value = scannedData;
                    // Cập nhật trạng thái ngay
                    setInputStatus(targetInput, 'success', validMatch.res.message);
                    if (validMatch.res.id_ct) targetInput.setAttribute('data-id-ct', validMatch.res.id_ct);
                    if (validMatch.res.available_count !== undefined) targetInput.setAttribute('data-available', validMatch.res.available_count);
                } else {
                    // Nếu không cái nào khớp DB, lấy cái đầu tiên như cũ nhưng báo lỗi validation
                    scannedData = result.results[0].data;
                    targetInput.value = scannedData;
                    await validateSerial(targetInput);
                }
                saveToLocalStorage(); // Lưu sau khi quét

                // Chuyển focus sang ô tiếp theo
                const allInputs = Array.from(inputs);
                const currentIndex = allInputs.indexOf(targetInput);
                if (currentIndex < allInputs.length - 1) {
                    allInputs[currentIndex + 1].focus();
                }
            } else {
                alert(result.message || 'Không tìm thấy mã QR hoặc Barcode trong ảnh. Hãy thử lại với ảnh rõ hơn.');
            }
        } catch (err) {
            console.error('Lỗi khi quét mã:', err);
            alert('Không thể kết nối tới server quét mã. Hãy đảm bảo server đang chạy tại ' + SCANNER_API_BASE);
        } finally {
            icon.innerHTML = originalIconHTML;
            icon.style.pointerEvents = 'auto';
        }
    }

    inputs.forEach((input, index) => {
        // Chuyển focus khi nhấn Enter
        input.addEventListener('keydown', async (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (index < inputs.length - 1) {
                    inputs[index + 1].focus();
                } else {
                    confirmBtn.focus();
                }
            }
        });

        // Tự động kiểm tra serial khi người dùng nhập hoặc thay đổi giá trị
        input.addEventListener('change', async (e) => {
            input.value = input.value.toUpperCase().trim();
            const val = input.value;
            if (val) {
                await validateSerial(input);
            }
        });

        // Tự động kiểm tra khi người dùng rời khỏi input (blur)
        input.addEventListener('blur', async (e) => {
            input.value = input.value.toUpperCase().trim();
            const val = input.value;
            if (val) {
                await validateSerial(input);
            } else {
                saveToLocalStorage(); // Lưu ngay cả khi xoá trống
            }
        });

        // Lưu bản nháp liên tục khi gõ phím để tránh mất dữ liệu nếu web bị treo/reload đột ngột
        input.addEventListener('input', () => {
            // Delay một chút để tránh spam localStorage quá nhiều
            if (input._saveTimer) clearTimeout(input._saveTimer);
            input._saveTimer = setTimeout(() => {
                saveToLocalStorage();
            }, 500);
        });
    });
    // Nút Clear input để nhập lại
    document.querySelectorAll('.clear-input-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const container = btn.closest('.comp-input-side');
            if (!container) return;
            const input = container.querySelector('.scan-input');
            const statusIcon = container.querySelector('.scan-status-icon');
            const errorMsg = container.querySelector('.scan-error-msg');

            const serialToClear = input.value.trim();
            const linhkienName = input.getAttribute('data-name');

            // Nếu có serial đang gán, thực hiện xoá trong database
            if (serialToClear !== '') {
                try {
                    const res = await fetch('ajax-xoa-serial.php', {
                        method: 'POST',
                        body: JSON.stringify({
                            order_id: (typeof currentOrderId !== 'undefined' ? currentOrderId : null),
                            so_serial: serialToClear,
                            ten_linhkien: linhkienName,
                            id_ct: input.getAttribute('data-id-ct'),
                            loai_linhkien: input.getAttribute('data-loai'), 
                            config_name: (typeof currentConfigName !== 'undefined' ? currentConfigName : '')
                        }),
                        headers: { 'Content-Type': 'application/json' }
                    });
                    const result = await res.json();
                    if (result.success) {
                        console.log('Đã xoá serial khỏi database:', serialToClear);
                    } else {
                        console.warn('Lỗi xoá database:', result.message);
                    }
                } catch (err) {
                    console.error('Lỗi kết nối khi xoá serial:', err);
                }
            }

            if (input) {
                input.value = '';
                input.setAttribute('data-old-serial', ''); // Reset cả old serial
                input.classList.remove('is-invalid', 'is-valid');
                input.focus();
            }
            if (statusIcon) {
                statusIcon.className = 'scan-status-icon';
                statusIcon.innerHTML = '';
            }
            if (errorMsg) {
                errorMsg.style.display = 'none';
                errorMsg.textContent = '';
            }
            saveToLocalStorage(); // Cập nhật localStorage sau khi xoá
        });
    });

    /**
     * Kiểm tra Serial
     */

    async function validateSerial(input) {
        input.value = input.value.toUpperCase().trim();
        const val = input.value;
        const parent = input.closest('.comp-input-side');
        const statusIcon = parent.querySelector('.scan-status-icon');
        const errorMsg = parent.querySelector('.scan-error-msg');
        const oldVal = input.getAttribute('data-old-serial') || '';

        try {
            // Nếu trùng với giá trị cũ đã được validate thành công thì không cần validate lại
            if (val === oldVal && input.classList.contains('is-valid')) {
                return true;
            }

            // Reset trạng thái
            input.classList.remove('is-invalid', 'is-valid');
            if (statusIcon) {
                statusIcon.className = 'scan-status-icon';
                statusIcon.innerHTML = '';
            }

            if (errorMsg) {
                errorMsg.style.display = 'none';
                errorMsg.textContent = '';
            }

            if (!val) {
                return false;
            }

            const orderIdVal = (typeof currentOrderId !== 'undefined') ? currentOrderId : 0;
            const cfgNameVal = (typeof currentConfigName !== 'undefined') ? currentConfigName : '';

            const response = await fetch('kiemtra.php?ajax=1', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_donhang: orderIdVal,
                    so_serial: val,
                    ten_linhkien: input.getAttribute('data-name') || '',
                    loai_linhkien: input.getAttribute('data-loai') || '',
                    config_name: cfgNameVal
                })
            });
            const result = await response.json();

            if (result.status === 'match') {
                setInputStatus(input, 'success', result.message);
                // Gán id_ct thật từ server trả về để lưu chính xác
                if (result.id_ct) input.setAttribute('data-id-ct', result.id_ct);
                if (result.available_count !== undefined) input.setAttribute('data-available', result.available_count);
                return true;
            } else {
                setInputStatus(input, 'error', result.message || 'Lỗi kiểm tra serial');
                return false;
            }
        } catch (e) {
            console.error("Lỗi validateSerial:", e);
            setInputStatus(input, 'error', 'Lỗi kết nối: ' + e.message);
            return false;
        } finally {
            saveToLocalStorage(); // Luôn lưu trạng thái mới nhất vào LocalStorage
        }
    }

    /* 
    async function saveSingleSerial(input, val, oldVal) {
        // Hàm này đã được tắt để tránh tự động lưu khi chưa xác nhận
    }
    */


    function setInputStatus(input, status, message = '') {
        const parent = input.closest('.comp-input-side');
        const statusIcon = parent.querySelector('.scan-status-icon');
        const errorMsg = parent.querySelector('.scan-error-msg');

        input.classList.remove('is-invalid', 'is-valid');
        if (statusIcon) statusIcon.className = 'scan-status-icon';
        if (errorMsg) {
            errorMsg.classList.remove('error', 'success');
            errorMsg.style.display = 'none';
        }

        if (status === 'success') {
            input.classList.add('is-valid');
            if (statusIcon) {
                statusIcon.classList.add('success');
                statusIcon.innerHTML = '<i class="fa-regular fa-circle-check"></i>';
            }
            if (errorMsg) {
                errorMsg.textContent = message || 'Serial trùng khớp với dữ liệu';
                errorMsg.classList.add('success');
                errorMsg.style.display = 'block';
            }
        } else if (status === 'error') {
            input.classList.add('is-invalid');
            if (statusIcon) {
                statusIcon.classList.add('error');
                statusIcon.innerHTML = '<i class="fa-solid fa-circle-xmark"></i>';
            }
            if (message) {
                if (errorMsg) {
                    errorMsg.textContent = message;
                    errorMsg.classList.add('error');
                    errorMsg.style.display = 'block';
                }
            }
        }
    }
    confirmBtn?.addEventListener('click', async () => {
        // Disable nút để tránh spam và hiện feedback đang kiểm tra
        const originalBtnText = confirmBtn.innerHTML;
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang kiểm tra...';

        // Bước 1: Validate tất cả serial (gọi server kiểm tra)
        try {
            await Promise.all(Array.from(inputs).map(input => validateSerial(input)));
        } catch (e) {
            console.error(e);
        }

        // Bước 2: Kiểm tra số lượng serial rảnh rỗi (QUAN TRỌNG: Phân loại theo linh kiện để tránh trùng mã CPU vs RAM)
        let typeSerialCounts = {}; // Cấu trúc: { "CPU_1": 1, "RAM_1": 2 }
        let duplicateExceeded = false;

        inputs.forEach(input => {
            const val = input.value.trim();
            const type = input.getAttribute('data-loai') || 'unknown';
            const name = input.getAttribute('data-name') || 'unknown';
            const typeKey = type + "_" + name + "_" + val;

            if (val && input.classList.contains('is-valid')) {
                typeSerialCounts[typeKey] = (typeSerialCounts[typeKey] || 0) + 1;

                // Lấy số lượng thực tế trong kho CHO LOẠI LINH KIỆN NÀY có Serial này
                const available = parseInt(input.getAttribute('data-available') || '1', 10);

                if (typeSerialCounts[typeKey] > available) {
                    duplicateExceeded = true;
                    setInputStatus(input, 'error', `Serial mã [${val}] cho máy chỉ còn ${available} cái, bạn nhập ${typeSerialCounts[typeKey]} cái là quá rồi!`);
                }
            }
        });

        if (duplicateExceeded) {
            alert('❌ Có serial bị nhập vượt quá số lượng linh kiện có sẵn trong kho! Vui lòng kiểm tra lại các ô báo đỏ.');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = originalBtnText;
            return;
        }

        // Bước 3: Kiểm tra có lỗi không (từ server trả về hoặc trống)
        const hasError = document.querySelectorAll('.scan-input.is-invalid').length > 0;
        if (hasError) {
            alert('❌ Dữ liệu không hợp lệ hoặc serial đã được sử dụng. Vui lòng kiểm tra lại các ô báo đỏ!');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = originalBtnText;
            return;
        }

        // Bước 4: Kiểm tra ĐÃ NHẬP ĐỦ serial chưa (quan trọng nhất)
        let serialsToSave = [];
        let missingFields = [];

        inputs.forEach((input, index) => {
            const val = input.value.trim();
            const linhkienName = input.getAttribute('data-name') || `Linh kiện ${index + 1}`;

            if (!val) {
                missingFields.push(linhkienName);
                // Highlight ô thiếu
                input.classList.add('is-invalid');
                const parent = input.closest('.comp-input-side');
                const errorMsg = parent?.querySelector('.scan-error-msg');
                if (errorMsg) {
                    errorMsg.textContent = 'Chưa nhập serial!';
                    errorMsg.style.display = 'block';
                }
            } else {
                serialsToSave.push({
                    val: val,
                    old_val: input.getAttribute('data-old-serial') || '',
                    name: input.getAttribute('data-name'),
                    type: input.getAttribute('data-loai'), // Thêm loại linh kiện
                    choice: input.getAttribute('data-choice'),
                    id_ct: parseInt(input.getAttribute('data-id-ct') || '0', 10) || 0
                });
            }
        });

        // Nếu còn thiếu serial → KHÔNG cho lưu
        if (missingFields.length > 0) {
            alert(`❌ Chưa nhập đủ serial!\n\nCòn thiếu: ${missingFields.join(', ')}`);
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = originalBtnText;
            return;
        }
        // Nếu đã đủ hết → mới cho lưu
        if (serialsToSave.length !== inputs.length) {
            alert('❌ Vui lòng nhập đầy đủ serial cho tất cả linh kiện!');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = originalBtnText;
            return;
        }
        // ======================
        // Bắt đầu lưu vào database
        // ======================
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu...';

        const formData = new FormData();
        formData.append('config_name', typeof currentConfigName !== 'undefined' ? currentConfigName : '');
        formData.append('order_id', typeof currentOrderId !== 'undefined' ? currentOrderId : null);

        serialsToSave.forEach((item, index) => {
            formData.append(`serials[${index}][val]`, item.val);
            formData.append(`serials[${index}][old_val]`, item.old_val);
            formData.append(`serials[${index}][name]`, item.name || '');
            formData.append(`serials[${index}][type]`, item.type || '');
            formData.append(`serials[${index}][choice]`, item.choice || '');
            if (item.id_ct > 0) formData.append(`serials[${index}][id_ct]`, String(item.id_ct));
        });

        fetch('ajax-luu-serial.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    clearLocalStorage(); // Xoá dữ liệu tạm sau khi lưu thành công vào DB
                    alert('✓ ' + (result.message || 'Đã lưu serial thành công!'));
                    const orderId = typeof currentOrderId !== 'undefined' ? currentOrderId :
                        (new URLSearchParams(window.location.search).get('id') || '');
                    window.location.href = `kho-hang.php?id=${orderId}`;
                } else {
                    alert('❌ Lỗi: ' + (result.message || 'Không thể lưu dữ liệu'));
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = 'Xác nhận Lưu <i class="fa-solid fa-paper-plane"></i>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Lỗi kết nối với server!');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = 'Xác nhận Lưu <i class="fa-solid fa-paper-plane"></i>';
            });
    });

});

