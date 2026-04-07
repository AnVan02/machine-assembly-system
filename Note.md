# PRODUCT_DOCUMENT

**localhost:**

| Status     | Mô tả                |
| ---------- | -------------------- |
| `database` | `"phan-mem-rap-may"` |
| `sql`      | `"root"`             |

---

-- Tạo key cho bảng

```json
{
   ALTER TABLE `donhang`
   ADD PRIMARY KEY (`id_donhang`);
}
```

-- Xoá dữ liệu hết bảng

```json
{
   TRUNCATE TABLE chitiet_donhang;
   TRUNCATE TABLE donhang;
}
```

** kiểm tra tồn tại hay không \***

```json

{
    SELECT * FROM `sanpham` WHERE SOSERIAL = "mã";
}
```

**Tích hợp AJAX: Giống như trang chính, trang test này giờ đây cũng sẽ tra cứu mã serial mà không bị load lại trang.**

```json
<?php
    // BƯỚC 1: Xử lý PHP nằm ngay trong file này
    if (isset($_POST['search'])) {
        $searchData = $_POST['search'];
        // Giả sử lấy dữ liệu từ DB hoặc API ở đây...
        $result = "Kết quả cho $searchData";
    }
?>
```

```json
<!-- BƯỚC 2: Cấu trúc HTML Form truyền thống -->
<div class="search-box">
    <form action="" method="POST"> <!-- Không có ID để JavaScript bắt sự kiện -->
        <input type="text" name="search" placeholder="Nhập mã serial..." required>

        <!-- NÚT NÀY KHI BẤM SẼ LOAD LẠI TRANG -->
        <button type="submit">Kiểm tra</button>
    </form>
</div>
```

```json
<!-- BƯỚC 3: Hiển thị kết quả -->
<div class="results">
    <?php if (isset($result)): ?>
        <p><?php echo $result; ?></p>
    <?php endif; ?>
</div>
```

<!-- KHÔNG CÓ ĐOẠN <script> NÀO ĐỂ CHẶN SỰ KIỆN SUBMIT -->
