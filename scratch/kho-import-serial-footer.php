   <div class="footer-actions">
      <button type="button" class="btn-back"
         onclick="window.location.href='kho-hang.php?id=<?php echo $order_id; ?>'">Quay lại</button>
      <button type="button" class="btn-confirm" id="btnConfirm">Xác nhận Lưu <i
            class="fa-solid fa-paper-plane"></i></button>
   </div>
</main>

<script>
   const currentOrderId = <?php echo json_encode($order_id); ?>;
   const currentConfigName = <?php echo json_encode($dNameMaster . ' | Máy ' . $m_idx_req); ?>;
</script>
<script src="./js/quet-ma.js?v=<?php echo time(); ?>"></script>
