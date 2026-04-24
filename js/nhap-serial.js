/**
 * nhap-serial.js - Redesigned
 * Tương ứng với thiết kế mới: accordion cards per component,
 * textarea realtime parse, progress tổng, upload excel
 */

document.addEventListener('DOMContentLoaded', () => {

   // ====== Dữ liệu trạng thái các linh kiện ======
   const componentState = {};

   // ====== Khởi tạo ======
   initAllCards();
   updateOverallProgress();

   // Hàm debounce để tự động lưu
   function debounce(func, timeout = 700) {
      let timer;
      return (...args) => {
         clearTimeout(timer);
         timer = setTimeout(() => { func.apply(this, args); }, timeout);
      };
   }

   const autoSave = debounce(async (id, card) => {
      const textarea = card.querySelector('.serial-textarea');
      const target = parseInt(card.dataset.target) || 0;
      const serials = parseSerials(textarea.value);

      if (serials.length > target) return;

      // Lưu Local
      const storageKey = `serials_${typeof currentOrderId !== 'undefined' ? currentOrderId : 'default'}_${id}`;
      localStorage.setItem(storageKey, JSON.stringify(serials));
      if (componentState[id]) {
         componentState[id].saved = serials;
      }

      // Cập nhật trạng thái card
      updateCardStatus(card, id, serials.length, target);
      // Cập nhật progress tổng
      updateOverallProgress();

      // Tự động đồng bộ với DB (Lưu nháp ngầm)
      // saveAllSerialsToDB(); // Đã tắt, chỉ lưu khi bấm xác nhận
   }, 1000);

   // ---------------------------------------------------
   // Khởi tạo textarea listener cho tất cả card
   // ---------------------------------------------------
   function initAllCards() {
      document.querySelectorAll('.component-card').forEach(card => {
         const id = card.dataset.id;
         const target = parseInt(card.dataset.target) || 0;
         const textarea = card.querySelector('.serial-textarea');
         if (!textarea) return;

         // Nếu php đã đổ serial ra textarea thì ưu tiên, không thì lấy localStorage
         let serials = parseSerials(textarea.value);
         if (serials.length === 0) {
            serials = getSavedSerials(id);
            if (serials.length > 0) {
               textarea.value = serials.join('\n');
            }
         }
         componentState[id] = {
            saved: serials,
            target: target
         };

         // Tự động chuyển khi gõ
         textarea.addEventListener('input', () => {
            // Tự động chuyển thành chữ thường ngay khi gõ
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const upperValue = textarea.value.toUpperCase();
            if (textarea.value !== upperValue) {
               textarea.value = upperValue;
               textarea.setSelectionRange(start, end);
            }

            const serials = parseSerials(textarea.value);
            updateCardDetected(card, id, serials);
            updateAssignmentPreview(card, serials); // Preview phân bổ
            updateOverallProgress(); // Cập nhật tổng ngay khi gõ

            // Tự động lưu
            autoSave(id, card); // Đã tắt để người dùng tự bấm lưu
         });

         // Dọn dẹp linh kiện khi rời khỏi ô nhập
         textarea.addEventListener('blur', () => {
            const serials = parseSerials(textarea.value);
            if (serials.length > 0) {
               // Sửa lỗi: Phải map ra string raw trước khi join
               textarea.value = serials.map(s => s.raw).join('\n');
            }
         });

         // Trigger lần đầu để hiển thị count và cập nhật Badge Trạng Thái
         const initSerials = parseSerials(textarea.value);
         updateCardDetected(card, id, initSerials);
         updateAssignmentPreview(card, initSerials); // Khởi tạo Preview
         updateCardStatus(card, id, componentState[id].saved.length, target);


         // upload file băng excel 
         const fileInput = card.querySelector('input[type=file]');
         if (fileInput) {
            fileInput.addEventListener('change', (e) => {
               handleExcelUpload(e, card, id);
            });
         }
      });

      // Sau khi init tất cả, cập nhật tổng một lần cuối
      updateOverallProgress();
   }


   // ---------------------------------------------------
   // Parse serial từ text (tách bởi newline hoặc dấu phẩy)
   // Tự động lọc trùng lặp
   // ---------------------------------------------------

   function parseSerials(text) {
      if (!text) return [];
      // Tách dòng nhưng KHÔNG lọc bỏ dòng trống hoàn toàn nếu nó chứa meta info (dấu |)
      // Mặc dù split \n thường giữ dòng trống, ta dùng regex linh hoạt hơn
      const lines = text.split(/\r?\n/);

      return lines.map(line => {
         // Hỗ trợ định dạng: SERIAL | MÁY | CẤU HÌNH BIỂU DIỄN
         const parts = line.split('|').map(p => p.trim());
         const serial = parts[0].replace(/\s+/g, '').toUpperCase();

         // Nếu có phần thứ 2, bóc tách số máy
         let manual_m = 0;
         if (parts[1]) {
            const mMatch = parts[1].match(/\d+/);
            manual_m = mMatch ? parseInt(mMatch[0]) : 0;
         }

         return {
            serial: serial,
            manual_m: manual_m,
            manual_choice: parts[2] || null,
            raw: line.trim()
         };
      }).filter(s => s.serial !== '' || s.manual_m > 0); // Chỉ giữ dòng có serial HOẶC có gán máy
   }

   function updateCardDetected(card, id, serials) {
      const detectedEl = card.querySelector(`#detected-${id}`);
      const errorMsgEl = card.querySelector(`#error-${id}`);
      const textarea = card.querySelector(`#textarea-${id}`);
      const target = parseInt(card.dataset.target) || 0;

      if (detectedEl) {
         detectedEl.textContent = serials.length;
         // Báo lỗi nếu nhập dư số lượng HOẶC trùng mã (áp dụng cho ram x2...)
         const snOnly = serials.map(s => s.serial);
         const seen = new Set();
         let hasDuplicate = false;
         for (const sn of snOnly) {
            if (sn && seen.has(sn)) { hasDuplicate = true; break; }
            seen.add(sn);
         }

         if (serials.length > target || hasDuplicate) {
            detectedEl.style.color = '#ef4444'; // Màu đỏ cảnh báo
            detectedEl.parentElement.style.fontWeight = 'bold';
            if (errorMsgEl) {
               errorMsgEl.style.display = 'block';
               if (hasDuplicate) {
                  errorMsgEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Lỗi: Phát hiện mã serial trùng lặp !';
               } else {
                  errorMsgEl.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> Lỗi: Không thể nhập thêm <span id="excess-${id}">${serials.length - target}</span> dữ liệu mới  `;
               }
            }
            if (textarea) {
               textarea.style.borderColor = '#ef4444';
               textarea.style.backgroundColor = '#FEF2F2';
               textarea.style.fontsize ='15px';
               textarea.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, .2)';
            }
         } else {
            detectedEl.style.color = '';
            detectedEl.parentElement.style.fontWeight = '';
            if (errorMsgEl) {
               errorMsgEl.style.display = 'none';
            }
            if (textarea) {
               textarea.style.borderColor = '';
               textarea.style.backgroundColor = '';
               textarea.style.boxShadow = '';
            }
         }
      }
   }

   // ---------------------------------------------------
   // Cập nhật xem trước phân bổ linh kiện vào máy
   // ---------------------------------------------------
   function updateAssignmentPreview(card, serials) {
      const id = card.dataset.id;
      const previewContainer = card.querySelector(`#preview-container-${id}`);
      const grid = card.querySelector(`#preview-grid-${id}`);
      if (!previewContainer || !grid) return;

      if (serials.length === 0) {
         previewContainer.style.display = 'none';
         return;
      }

      previewContainer.style.display = 'block';
      grid.innerHTML = '';

      const target = parseInt(card.dataset.target) || 0;

      for (let i = 0; i < target; i++) {
         const sData = serials[i];
         const serial = sData ? sData.serial : '';

         let label = '<span style="color:#94a3b8">Trống</span>';
         if (sData && sData.manual_m > 0) {
            label = `<span style="font-weight:700; color:#2563eb">Máy ${sData.manual_m}</span> <span style="opacity:0.6">${sData.manual_choice || ''}</span>`;
         } else if (sData) {
            label = `<span style="color:#64748b; font-weight:500">Chưa gán máy</span>`;
         }


         const itemEl = document.createElement('div');
         itemEl.className = 'assignment-preview-item';
         Object.assign(itemEl.style, {
            background: serial ? '#f0fdf4' : '#f8fafc',
            border: `1px solid ${serial ? '#bbf7d0' : '#e2e8f0'}`,
            padding: '6.5px 10px',
            borderRadius: '8px',
            fontSize: '11px',
            transition: 'all 0.2s ease',
            display: 'flex',
            flexDirection: 'column',
            justifyContent: 'center'
         });

         itemEl.innerHTML = `
            <div style="color: #64748b; margin-bottom: 2.5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">${label}</div>
            <div style="color: ${serial ? '#166534' : '#94a3b8'}; font-family: 'Outfit', 'Segoe UI', monospace; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border-top: 1px solid ${serial ? 'rgba(0,0,0,0.05)' : 'transparent'}; padding-top:2px" title="${serial}">
               ${serial || '---'}
            </div>
         `;
         grid.appendChild(itemEl);
      }
   }

   function checkDuplicateSerialsInGroup() {
      const cards = document.querySelectorAll('.component-card');
      let hasWarning = false;
      let msg = [];

      cards.forEach(card => {
         const textarea = card.querySelector('.serial-textarea');
         if (!textarea) return;

         const serials = parseSerials(textarea.value);
         if (serials.length === 0) return;

         // Tìm các mã trùng TRONG CÙNG MỘT CARD (áp dụng cho mục có quantity 2+, linh kiện lẻ...)
         const seen = new Set();
         const localDuplicates = new Set();
         serials.forEach(sn => {
            if (seen.has(sn)) {
               localDuplicates.add(sn);
            } else {
               seen.add(sn);
            }
         });

         if (localDuplicates.size > 0) {
            hasWarning = true;
            const compName = card.querySelector('.comp-name')?.textContent || 'Linh kiện';
            msg.push(`[${compName}] trùng mã: ${Array.from(localDuplicates).slice(0, 3).join(', ')}`);

            textarea.style.borderColor = '#ef4444';
            textarea.style.backgroundColor = '#FEF2F2';
         } else {
            // Không reset style ở đây vì updateCardDetected đã lo việc này real-time
            // Hoặc có thể reset nếu muốn chắc chắn
         }
      });

      return { isValid: !hasWarning, messages: msg };
   }
   // ---------------------------------------------------
   // Lưu serial cho một linh kiện
   // ---------------------------------------------------
   window.saveSerial = function (id, btn) {
      const card = document.querySelector(`.component-card[data-id="${id}"]`);
      const textarea = card.querySelector('.serial-textarea');
      const target = parseInt(card.dataset.target) || 0;
      const serials = parseSerials(textarea.value);


      // Kiểm tra nếu nhập dư số lượng
      if (serials.length > target) {
         showToast(`❌ Lỗi: Bạn đã nhập ${serials.length} serial, nhưng chỉ cần ${target}. Vui lòng kiểm tra lại!`, 'warning');
         return; // Không cho lưu nếu dư
      }

      // Cập nhật lại textarea với dữ liệu đã chuẩn hóa
      textarea.value = serials.map(s => s.raw).join('\n');

      // Lưu vào localStorage (mock)
      const storageKey = `serials_${typeof currentOrderId !== 'undefined' ? currentOrderId : 'default'}_${id}`;
      localStorage.setItem(storageKey, JSON.stringify(serials));
      componentState[id].saved = serials;

      // Cập nhật trạng thái card
      updateCardStatus(card, id, serials.length, target);

      // Cập nhật tổng progress
      updateOverallProgress();
      showToast(`Đã lưu ${serials.length} serial cho ${getCompName(card)}`, 'success');
   };

   // ---------------------------------------------------
   // Cập nhật trạng thái badge của card (sau khi lưu)
   // ---------------------------------------------------

   function updateCardStatus(card, id, count, target) {
      const statusEl = card.querySelector('.comp-status');
      const actionWrap = card.querySelector('.header-action-wrap') || card.querySelector('.comp-status-area');

      // Xóa class cũ
      card.classList.remove('done');
      statusEl.classList.remove('status-active', 'status-pending', 'status-done');

      // Xóa btn old
      const oldButtons = actionWrap.querySelectorAll('button');
      oldButtons.forEach(b => b.remove());

      if (count === 0) {
         // Chưa nhập
         statusEl.classList.add('status-pending');
         statusEl.textContent = `Chưa nhập (0/${target})`;

         const btn = createBtn('btn-nhap-serial', '<i class="fa-solid fa-circle-plus"></i> Nhập Serial', () => expandCard(card));
         actionWrap.appendChild(btn);

      } else if (count >= target) {
         // Hoàn thành
         card.classList.add('done');
         statusEl.classList.add('status-done');
         statusEl.textContent = `Hoàn thành (${count}/${target})`;

         const btn = createBtn('btn-edit-serial', '<i class="fa-solid fa-pencil"></i> Chỉnh sửa', () => expandCard(card));
         actionWrap.appendChild(btn);


         // Cập nhật icon màu
         const icon = card.querySelector('.comp-icon');
         if (icon) {
            icon.style.background = '#b3cdff33';
            icon.style.color = '#1152D4';
         }
      } else {
         // Đang nhập
         statusEl.classList.add('status-active');
         statusEl.textContent = `Đang nhập (${count}/${target})`;
      }
   }
   function createBtn(className, html, onClick) {
      const btn = document.createElement('button');
      btn.className = className;
      btn.innerHTML = html;
      btn.addEventListener('click', (e) => {
         e.stopPropagation();
         onClick();
      });
      return btn;
   }


   // ---------------------------------------------------
   // Toggle open/close card body: mở đóng giao diện linh kiên
   // ---------------------------------------------------
   window.toggleCard = function (header) {
      const card = header.closest('.component-card');
      card.classList.toggle('open');
   };

   window.expandCard = function (card) {
      card.classList.add('open');
      card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
   };

   // ---------------------------------------------------
   // Overall progress 
   // ---------------------------------------------------

   function updateOverallProgress() {
      let totalDone = 0;
      let totalAll = 0;
      let hasExcessive = false;

      document.querySelectorAll('.component-card').forEach(card => {
         const id = card.dataset.id;
         const target = parseInt(card.dataset.target) || 0;

         // Lấy số lượng từ textarea (nếu có) để cập nhật thời gian thực
         const textarea = card.querySelector('.serial-textarea');
         const currentCount = textarea ? parseSerials(textarea.value).length : (componentState[id]?.saved?.length || 0);

         totalAll += target;
         totalDone += Math.min(currentCount, target);

         if (currentCount > target) {
            hasExcessive = true;
         }
      });

      const pct = totalAll > 0 ? Math.round((totalDone / totalAll) * 100) : 0;

      const percentEl = document.getElementById('overallPercent');
      const fillEl = document.getElementById('overallProgressFill');
      const doneEl = document.getElementById('totalDoneSerial');
      const allEl = document.getElementById('totalAllSerial');

      if (percentEl) percentEl.textContent = `${pct}%`;
      if (fillEl) fillEl.style.width = `${pct}%`;
      if (doneEl) doneEl.textContent = totalDone;
      if (allEl) allEl.textContent = totalAll;

      // Khóa/Mở nút Xác nhận: Chỉ mở khi đã nhập đủ tất cả serial và không có cái nào bị dư
      const btnXacNhan = document.getElementById('btnXacNhan');
      if (btnXacNhan) {
         // Luôn mở nút để người dùng có thể click và nhận thông báo nhắc nhở nếu chưa đủ
         // btnXacNhan.disabled = false; 

         // Có thể thêm hiệu ứng mờ nếu chưa hoàn thành (tùy chọn)
         if (pct === 100 && totalDone === totalAll && !hasExcessive) {
            btnXacNhan.classList.add('ready');
         } else {
            btnXacNhan.classList.remove('ready');
         }
      }
   }


   // ---------------------------------------------------
   // Tải dữ liệu từ localStorage (mock backend)
   // ---------------------------------------------------

   function getSavedSerials(id) {
      try {
         const storageKey = `serials_${typeof currentOrderId !== 'undefined' ? currentOrderId : 'default'}_${id}`;
         return JSON.parse(localStorage.getItem(storageKey)) || [];
      } catch {
         return [];
      }
   }
   function getCompName(card) {
      return card.querySelector('.comp-name')?.textContent || 'linh kiện';
   }

   // ---------------------------------------------------
   // Upload file excel 
   // ---------------------------------------------------
   function handleExcelUpload(e, card, id) {
      const file = e.target.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = (ev) => {
         const text = ev.target.result;
         const textarea = card.querySelector('.serial-textarea');
         const existing = textarea.value.trim();
         textarea.value = existing ? existing + '\n' + text : text;
         textarea.dispatchEvent(new Event('input'));
         showToast('Đã tải file lên, vui lòng kiểm tra danh sách serial.');
      };
      reader.readAsText(file);

      // Reset input để cho phép chọn lại cùng file
      e.target.value = '';
   }

   // ---------------------------------------------------
   // button ở chân trang footer 
   // ---------------------------------------------------
   document.getElementById('btnLuuNhap')?.addEventListener('click', async () => {
      // Kiểm tra dư thừa trước khi lưu tất cả
      let excessive = [];
      document.querySelectorAll('.component-card').forEach(card => {
         const id = card.dataset.id;
         const target = parseInt(card.dataset.target) || 0;
         const textarea = card.querySelector('.serial-textarea');
         if (!textarea) return;
         const serials = parseSerials(textarea.value);
         if (serials.length > target) {
            excessive.push(card.querySelector('.comp-name')?.textContent);
         }
      });

      if (excessive.length > 0) {
         showToast(`❌ Không thể lưu! Các linh kiện sau đang bị dư serial: ${excessive.join(', ')}`, 'warning');
         return;
      }

      const dupCheck = checkDuplicateSerialsInGroup();
      if (!dupCheck.isValid) {
         showToast(`❌ Lỗi nhập trùng: ${dupCheck.messages.join(' | ')}`, 'warning');
         return;
      }
      // Lưu tất cả cards vào localStorage trước 
      document.querySelectorAll('.component-card').forEach(card => {
         const id = card.dataset.id;
         const textarea = card.querySelector('.serial-textarea');
         if (!textarea) return;
         const serials = parseSerials(textarea.value);
         const storageKey = `serials_${typeof currentOrderId !== 'undefined' ? currentOrderId : 'default'}_${id}`;
         localStorage.setItem(storageKey, JSON.stringify(serials));
         componentState[id].saved = serials;
         updateCardStatus(card, id, serials.length, parseInt(card.dataset.target) || 0);
      });
      updateOverallProgress();

      // Đồng bộ lên DB để dashboard-ke-toan và dashboard-ad.php cập nhật ngay
      showToast('Đang đồng bộ dữ liệu với SQL...', 'info');
      const result = await manualSaveSerialsToDB();

      if (result.success) {
         showToast('✓ Lưu nháp thành công.', 'success');
         setTimeout(() => {
            const orderId = typeof currentOrderId !== 'undefined' ? currentOrderId : null;
            window.location.href = orderId ? `dashboard-ke-toan.php?updated=1&id=${orderId}` : 'dashboard-ke-toan.php?updated=1';
         }, 800);
      } else {
         showToast('Lỗi lưu SQL: ' + (result?.message || 'Không rõ lỗi'), 'warning');
      }
   });



   document.getElementById('btnXacNhan')?.addEventListener('click', async () => {
      // Kiểm tra linh kiện chưa đủ hoặc dư serial
      let incomplete = [];
      let excessive = [];
      document.querySelectorAll('.component-card').forEach(card => {
         const id = card.dataset.id;
         const target = parseInt(card.dataset.target) || 0;
         const textarea = card.querySelector('.serial-textarea');
         const currentSerials = textarea ? parseSerials(textarea.value) : (componentState[id]?.saved || []);
         const count = currentSerials.length;
         if (count < target) {
            incomplete.push(card.querySelector('.comp-name')?.textContent);
         } else if (count > target) {
            excessive.push(card.querySelector('.comp-name')?.textContent);
         }
      });

      if (excessive.length > 0) {
         showToast(`❌ Lỗi: Có linh kiện bị dư serial: ${excessive.join(', ')}`, 'warning');
         return;
      }

      const dupCheck = checkDuplicateSerialsInGroup();
      if (!dupCheck.isValid) {
         showToast(`❌ Lỗi nhập trùng: ${dupCheck.messages.join(' | ')}`, 'warning');
         return;
      }

      if (incomplete.length > 0) {
         showToast(`⚠ chua nhập đủ serial mac dinh`, 'warning');
         return;
      }

      // Lưu vào DB trước khi chuyển trang
      showToast('Đang đồng bộ dữ liệu với SQL...', 'info');
      const result = await manualSaveSerialsToDB();

      if (result.success) {
         showToast('✓ Lưu thành công so-serial', 'success');
         setTimeout(() => {
            const orderId = typeof currentOrderId !== 'undefined' ? currentOrderId : null;
            window.location.href = orderId ? `dashboard-ke-toan.php?updated=1&id=${orderId}` : 'dashboard-ke-toan.php?updated=1';
         }, 1500);
      } else {
         showToast('Lỗi lưu SQL: ' + result.message, 'warning');
      }
   });

   /**
    * Thu thập serial từ tất cả card và gửi lên server
    */
   async function manualSaveSerialsToDB() {
      const serials_data = [];
      const order_id = typeof currentOrderId !== 'undefined' ? currentOrderId : 1;

      document.querySelectorAll('.component-card').forEach(card => {
         const textarea = card.querySelector('.serial-textarea');
         if (textarea) {
            const serials = parseSerials(textarea.value);
            // Gửi dữ liệu đi ngay cả khi serials trống để xóa sạch trong DB nếu cần
            serials_data.push({
               type: card.dataset.type,
               name: card.dataset.name,
               config: card.dataset.config,
               linhkien_chon: card.dataset.choice || card.dataset.config || '',
               auto_assign: document.getElementById('chkAutoAssign')?.checked ?? true,
               serials: serials // Gửi cả object {serial, manual_m, manual_choice}
            });
         }
      });

      try {
         const response = await fetch('luu-serial-db.php', {
            method: 'POST',
            body: JSON.stringify({
               order_id: order_id,
               serials_data: serials_data
            }),
            headers: {
               'Content-Type': 'application/json'
            }
         });
         return await response.json();
      } catch (err) {
         return { success: false, message: 'Lỗi kết nối server' };
      }
   }

   // ---------------------------------------------------
   // Toast notification (nhẹ, không dùng alert)
   // ---------------------------------------------------
   function showToast(message, type = 'info') {
      const existing = document.querySelector('.serial-toast');
      if (existing) existing.remove();

      const toast = document.createElement('div');
      toast.className = `serial-toast serial-toast--${type}`;
      toast.textContent = message;

      Object.assign(toast.style, {
         position: 'fixed',
         bottom: '1.5rem',
         right: '1.5rem',
         background: type === 'warning' ? '#fef3c7' : type === 'success' ? '#f0fdf4' : '#eff6ff',
         color: type === 'warning' ? '#92400e' : type === 'success' ? '#15803d' : '#1e40af',
         border: `1px solid ${type === 'warning' ? '#fde68a' : type === 'success' ? '#bbf7d0' : '#bfdbfe'}`,
         borderRadius: '10px',
         padding: '0.75rem 1.25rem',
         fontSize: '0.88rem',
         fontWeight: '500',
         boxShadow: '0 4px 16px rgba(0,0,0,.1)',
         zIndex: 9999,
         animation: 'toastIn .25s ease',
         maxWidth: '360px',
      });

      document.body.appendChild(toast);

      // CSS animation
      if (!document.getElementById('toast-style')) {
         const style = document.createElement('style');
         style.id = 'toast-style';
         style.textContent = `
         @keyframes toastIn {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
         }
      `;
         document.head.appendChild(style);
      }

      setTimeout(() => toast.remove(), 3000);
   }

});
