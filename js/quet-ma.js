// CHỨC NĂNG QUÉT MÃ VÀ LƯU SERIAL - BẢN CAO CẤP V8
(function () {
    console.log('Hệ thống Quét mã (Premium V8) đã nạp!');

    const sounds = {
        success: document.getElementById('sound-success'),
        error: document.getElementById('sound-error')
    };

    function playSound(type) {
        // Âm thanh đã bị vô hiệu hoá theo yêu cầu
    }

    function showToast(message, type = 'success') {
        const toast = document.getElementById('scan-toast');
        if (!toast) return;
        // Chuyển đổi \n thành <br> để hiển thị nhiều dòng
        toast.innerHTML = message.replace(/\n/g, '<br>');
        toast.className = `scan-toast show ${type}`;
        
        // Tăng thời gian hiển thị nếu là tin nhắn dài (thành công)
        const duration = type === 'success' ? 3500 : 3000;
        setTimeout(() => toast.classList.remove('show'), duration);
    }

    function showFeedback(input, message, type) {
        let parent = input.closest('.comp-input-side');
        if (!parent) return;
        
        let feedback = parent.querySelector('.input-feedback-msg');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'input-feedback-msg';
            parent.appendChild(feedback);
        }
        
        feedback.innerHTML = message.replace(/\n/g, '<br>');
        feedback.className = `input-feedback-msg show ${type}`;
        
        if (type === 'success') {
            setTimeout(() => {
                feedback.classList.remove('show');
            }, 6000); // Ẩn thông báo xanh sau 6s
        }
    }

    // --- LOCAL STORAGE TỰ ĐỘNG LƯU NHÁP ---
    function getStorageKey() {
        const orderId = typeof currentOrderId !== 'undefined' ? currentOrderId : 'none';
        const configName = typeof currentConfigPure !== 'undefined' ? currentConfigPure : 'none';
        const machineIdx = typeof currentMachineIdx !== 'undefined' ? currentMachineIdx : 'none';
        return `RAPMAY_DRAFT_${orderId}_${configName}_${machineIdx}`;
    }

    function saveDraftToStorage() {
        const data = {};
        document.querySelectorAll('.scan-input').forEach(input => {
            const id = input.getAttribute('data-id-ct');
            if (id && input.value.trim() && input.classList.contains('is-valid')) {
                data[id] = input.value.trim();
            }
        });
        localStorage.setItem(getStorageKey(), JSON.stringify(data));
    }

    function loadDraftFromStorage() {
        try {
            const saved = localStorage.getItem(getStorageKey());
            if (saved) {
                const data = JSON.parse(saved);
                document.querySelectorAll('.scan-input').forEach(input => {
                    const id = input.getAttribute('data-id-ct');
                    // Chỉ nạp nếu ô đang trống (tránh đè lên dữ liệu đã lưu DB do Server nạp ra)
                    if (id && data[id] && input.value.trim() === '') {
                        input.value = data[id];
                        // Validate ngay để lấy lại dấu check xanh
                        validateSerial(input);
                    }
                });
            }
        } catch (e) {
            console.error('Lỗi nạp Draft:', e);
        }
    }

    function clearDraftStorage() {
        localStorage.removeItem(getStorageKey());
    }
    // --- KẾT THÚC LOCAL STORAGE ---

    async function validateSerial(input) {
        const val = input.value.trim();
        const wrapper = input.closest('.input-wrapper');
        const icon = wrapper ? wrapper.querySelector('.status-indicator') : null;

        if (val === '') {
            input.classList.remove('is-valid', 'is-invalid', 'is-loading');
            if (icon) icon.innerHTML = '';
            return false;
        }

        // --- KIỂM TRA TRÙNG LẶP TRÊN GIAO DIỆN ---
        const allIns = document.querySelectorAll('.scan-input');
        let isDup = false;
        allIns.forEach(other => {
            if (other !== input && other.value.trim().toUpperCase() === val.toUpperCase() && val !== '') {
                // CHỈ báo trùng nếu CÙNG LOẠI linh kiện (Ví dụ: RAM quét 2 lần)
                if (other.getAttribute('data-loai') === input.getAttribute('data-loai')) {
                    isDup = true;
                }
            }
        });

        if (isDup) {
            input.classList.remove('is-valid', 'is-loading');
            input.classList.add('is-invalid');
            if (icon) {
                icon.innerHTML = '<i class="fa-solid fa-circle-exclamation" style="color:#ef4444"></i>';
                icon.className = 'status-indicator error anim-shake';
            }
            playSound('error');
            showFeedback(input, 'Lỗi: Mã RAM/Linh kiện này đã được nhập ở ô khác!', 'error');
            return false;
        }

        input.classList.add('is-loading');
        if (icon) icon.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

        const type = input.getAttribute('data-loai');
        const name = input.getAttribute('data-name');
        const id_ct = input.getAttribute('data-id-ct');
        const orderId = typeof currentOrderId !== 'undefined' ? currentOrderId : null;

        try {
            const resp = await fetch('kiemtra.php?ajax=1', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_donhang: orderId,
                    so_serial: val,
                    loai_linhkien: type,
                    ten_linhkien: name,
                    id_ct: id_ct,
                    config_name: typeof currentConfigPure !== 'undefined' ? currentConfigPure : '',
                    machine_idx: typeof currentMachineIdx !== 'undefined' ? currentMachineIdx : 1
                })
            });
            const res = await resp.json();

            input.classList.remove('is-loading');
            if (res.status === 'match') {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                if (res.id_ct) {
                    input.setAttribute('data-id-ct', res.id_ct);
                }
                if (icon) {
                    icon.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#22c55e"></i>';
                    icon.className = 'status-indicator success anim-pop';
                }
                playSound('success');
                showFeedback(input, res.message, 'success');
                saveDraftToStorage(); // <-- Lưu nháp khi quét đúng
                return true;
            } else {
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
                if (icon) {
                    icon.innerHTML = '<i class="fa-solid fa-circle-xmark" style="color:#ef4444"></i>';
                    icon.className = 'status-indicator error anim-shake';
                }
                playSound('error');
                showFeedback(input, res.message, 'error');
                return false;
            }
        } catch (e) {
            input.classList.remove('is-loading');
            console.error('Lỗi kiểm tra:', e);
            return false;
        }
    }

    // (reload trang) sẽ KHÔNG làm mất serial vì bạn đã có cơ chế lưu LocalStorage.
    function initScanSystem() {
        const confirmBtn = document.getElementById('btnConfirm');
        const allIns = document.querySelectorAll('.scan-input');
        if (!confirmBtn) return;
        
        // Khôi phục dữ liệu nháp từ LocalStorage
        loadDraftFromStorage();

        // --- TÍCH HỢP QUÉT MÃ QUA ĐIỆN THOẠI CAMERA ---
        const scannerModal = document.getElementById('scanner-ui-modal');
        const cameraInput = document.getElementById('scan-file-input');
        const previewArea = document.getElementById('modalPreviewArea');
        const previewImg = document.getElementById('modal-preview-img');
        const placeholder = document.getElementById('modalPlaceholder');
        const scannerStatus = document.getElementById('modalStatus');
        const modalLoading = document.getElementById('modalLoading');
        const loadingText = document.getElementById('loadingTextModal');
        const btnCapture = document.getElementById('btnModalCapture');
        const btnScan = document.getElementById('btnModalScan');
        const resultArea = document.getElementById('modalResultArea');
        
        const PROXY_URL = 'scanner-proxy.php?path=';
        let currentTargetInput = null;
        let selectedFile = null;

        if (scannerModal) {
            // Close buttons
            document.querySelectorAll('.btn-close-scanner').forEach(btn => {
                btn.addEventListener('click', () => {
                    scannerModal.style.display = 'none';
                    currentTargetInput = null;
                });
            });

            // Trigger file selection
            const triggerFileSelect = () => cameraInput.click();
            previewArea.addEventListener('click', triggerFileSelect);
            btnCapture.addEventListener('click', triggerFileSelect);

            cameraInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;
                
                selectedFile = file;
                const reader = new FileReader();
                reader.onload = (event) => {
                    previewImg.src = event.target.result;
                    previewImg.style.display = 'block';
                    placeholder.style.display = 'none';
                    btnScan.disabled = false;
                    scannerStatus.textContent = "Đã nhận ảnh. Nhấn 'Xử lý' để tiếp tục.";
                    scannerStatus.className = 'scanner-status-text success';
                };
                reader.readAsDataURL(file);
            });

            btnScan.addEventListener('click', async () => {
                if (!selectedFile) return;
                modalLoading.style.display = 'flex';
                loadingText.textContent = "Đang phân tích mã vạch...";
                btnScan.disabled = true;
                btnCapture.disabled = true;

                const formData = new FormData();
                formData.append('file', selectedFile);

                try {
                    const res = await fetch(PROXY_URL + 'scan', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();

                    if (result.success && result.results && result.results.length > 0) {
                        const data = result.results[0].data;
                        scannerStatus.textContent = "✓ Quét mã thành công!";
                        scannerStatus.className = 'scanner-status-text success';
                        
                        resultArea.style.display = 'block';
                        resultArea.innerHTML = `<i class="fa-solid fa-check-circle"></i> Mã: <b>${data}</b>`;
                        
                        if (currentTargetInput) {
                            currentTargetInput.value = data;
                            // Tự động trigger Enter để xác thực mã
                            setTimeout(() => {
                                scannerModal.style.display = 'none';
                                const event = new KeyboardEvent('keydown', { key: 'Enter' });
                                currentTargetInput.dispatchEvent(event);
                            }, 1200);
                        }
                    } else {
                        scannerStatus.textContent = "❌ Không tìm thấy mã QR hoặc Barcode trong ảnh. Hãy thử lại với ảnh rõ hơn.";
                        scannerStatus.className = 'scanner-status-text error';
                    }
                } catch (err) {
                    scannerStatus.textContent = "❌ Không thể kết nối tới server quét mã .";
                    scannerStatus.className = 'scanner-status-text error';
                } finally {
                    modalLoading.style.display = 'none';
                    if(!resultArea.style.display || resultArea.style.display === 'none') {
                        btnScan.disabled = false;
                    }
                    btnCapture.disabled = false;
                }
            });
            
            // Gắn sự kiện cho các icon quét mã
            document.querySelectorAll('.scan-btn-icon').forEach(icon => {
                icon.addEventListener('click', function() {
                    const row = this.closest('.input-wrapper');
                    const input = row ? row.querySelector('.scan-input') : null;
                    if (input) {
                        currentTargetInput = input;
                        
                        // Reset modal state
                        selectedFile = null;
                        cameraInput.value = '';
                        previewImg.style.display = 'none';
                        previewImg.src = '';
                        placeholder.style.display = 'flex';
                        scannerStatus.textContent = 'Chưa chọn ảnh nào';
                        scannerStatus.className = 'scanner-status-text';
                        btnScan.disabled = true;
                        resultArea.style.display = 'none';
                        resultArea.innerHTML = '';
                        
                        scannerModal.style.display = 'flex';
                    }
                });
            });
        }
        // --- KẾT THÚC TÍCH HỢP ---

        // Tự động focus ô TRỐNG đầu tiên khi mở trang
        const firstEmpty = Array.from(allIns).find(i => !i.value.trim() || i.classList.contains('is-invalid'));
        if (firstEmpty) firstEmpty.focus();

        allIns.forEach((input, idx) => {
            // Khi quét xong (Enter)
            input.addEventListener('keydown', async (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();

                    // Nếu ô đang có dữ liệu và đã valid, chỉ cần nhảy, không cần re-validate (tiết kiệm thời gian)
                    const val = input.value.trim();
                    let isValid = false;
                    if (val !== '' && input.classList.contains('is-valid')) {
                        isValid = true;
                    } else {
                        isValid = await validateSerial(input);
                    }

                    if (isValid) {
                        // NHẢY THÔNG MINH: Tìm ô trống tiếp theo (bỏ qua các ô đã tích xanh)
                        const nextInputs = Array.from(allIns).slice(idx + 1);
                        const nextEmpty = nextInputs.find(i => !i.value.trim() || i.classList.contains('is-invalid'));

                        if (nextEmpty) {
                            nextEmpty.focus();
                        } else {
                            // Nếu tất cả đã xong, focus nút Lưu
                            confirmBtn.focus();
                            // Tùy chọn: Tự động nhấn lưu nếu cấu hình cho phép
                            // confirmBtn.click(); 
                        }
                    } else {
                        input.select(); // Bôi đen để quét lại nếu lỗi
                    }
                }
            });

            // Khi mất focus
            input.addEventListener('blur', () => {
                if (input.value.trim()) validateSerial(input);
            });
        });

        confirmBtn.onclick = async function () {
            const invalid = document.querySelector('.scan-input.is-invalid');
            if (invalid) {
                showToast('Vui lòng sửa các ô báo đỏ!', 'error');
                invalid.focus();
                return;
            }

            const serialsData = [];
            let allFilled = true;
            allIns.forEach(input => {
                const val = input.value.trim();
                if (val === '') allFilled = false;
                else {
                    serialsData.push({
                        val: val,
                        name: input.getAttribute('data-name'),
                        type: input.getAttribute('data-loai'),
                        choice: input.getAttribute('data-choice'),
                        id_ct: input.getAttribute('data-id-ct')
                    });
                }
            });

            if (!allFilled) {
                if (!confirm('Một số linh kiện còn trống. Bạn vẫn muốn lưu?')) return;
            }

            if (serialsData.length === 0) {
                showToast('Chưa có dữ liệu để lưu!', 'error');
                return;
            }

            const originalHTML = this.innerHTML;
            try {
                this.disabled = true;
                this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu...';

                const fd = new FormData();
                fd.append('order_id', typeof currentOrderId !== 'undefined' ? currentOrderId : '');
                fd.append('config_name', typeof currentConfigPure !== 'undefined' ? currentConfigPure : '');
                fd.append('machine_idx', typeof currentMachineIdx !== 'undefined' ? currentMachineIdx : 1);
                serialsData.forEach((item, i) => {
                    fd.append(`serials[${i}][val]`, item.val);
                    fd.append(`serials[${i}][name]`, item.name);
                    fd.append(`serials[${i}][type]`, item.type);
                    fd.append(`serials[${i}][choice]`, item.choice);
                    fd.append(`serials[${i}][id_ct]`, item.id_ct);
                });

                const resp = await fetch('ajax-luu-serial.php', { method: 'POST', body: fd });
                const res = await resp.json();

                if (res.success) {
                    clearDraftStorage(); // <-- Xoá nháp khi lưu DB thành công
                    showToast('✓ ' + (res.message || 'Thành công!'), 'success');
                    setTimeout(() => {
                        const nextUrl = confirmBtn.getAttribute('data-next-url');
                        if (nextUrl) {
                            window.location.href = nextUrl;
                        } else {
                            window.location.href = 'kho-hang.php?id=' + (typeof currentOrderId !== 'undefined' ? currentOrderId : '');
                        }
                    }, 800);
                } else {
                    showToast('Lỗi: ' + res.message, 'error');
                    this.disabled = false; this.innerHTML = originalHTML;
                }
            } catch (err) {
                showToast('Lỗi kết nối!', 'error');
                this.disabled = false; this.innerHTML = originalHTML;
            }
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initScanSystem);
    } else {
        initScanSystem();
    }
})();
