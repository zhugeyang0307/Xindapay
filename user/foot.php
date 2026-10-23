
<?php 
if (function_exists('is_mobile_router') && is_mobile_router()) {
    include __DIR__ . '/m_foot.php';
    return; // 手机端输出完毕，退出
}
?>
  <!-- / content -->

  <!-- footer -->
  <footer id="footer" class="app-footer" role="footer">
        <div class="wrapper b-t bg-light text-center" style="padding:15px;line-height:1.9;color:#888;font-size:13px;">
    </div>
  </footer>
  <!-- / footer -->

</div>

<script src="<?php echo $cdnpublic?>jquery/3.4.1/jquery.min.js"></script>
<script src="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script src="/user/assets/js/ui-load.js"></script>
<script src="/user/assets/js/ui-jp.config.js"></script>
<script src="/user/assets/js/ui-jp.js"></script>
<script src="/user/assets/js/ui-nav.js"></script>
<script src="/user/assets/js/ui-toggle.js"></script>

<script>
// ===== 订单提示音与系统通知引擎 =====
(function(){
    let lastCheckTime = Math.floor(Date.now() / 1000);
    let isFirstRun = true;
    let audioCtx = null;

    // 合成提示音（不需要外链MP3，秒加载）
    function playSound() {
        try {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') audioCtx.resume();
            
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(800, audioCtx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(1200, audioCtx.currentTime + 0.1);
            gain.gain.setValueAtTime(0.5, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.5);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.5);
        } catch(e) { console.log('Audio error', e); }
    }

    // 弹系统通知
    function showNotify(amount, trade_no) {
        if (!("Notification" in window)) return;
        if (Notification.permission === "granted") {
            new Notification("💰 新订单到账", {
                body: "收款金额: ￥" + amount + "\n订单号: " + trade_no,
                icon: "/assets/img/app-icon.svg",
                tag: "new-order"
            });
        }
    }

    // 轮询检查
    function checkNewOrder() {
        // 请求最新的一条订单
        $.ajax({
            type: 'POST',
            url: '/user/ajax2.php?act=orderList',
            data: { limit: 1, offset: 0, order: 'addtime desc' },
            dataType: 'json',
            success: function(res) {
                if (!res || !res.rows || res.rows.length === 0) return;
                let topOrder = res.rows[0];
                
                // 解析订单时间戳
                let orderTime = new Date(topOrder.addtime).getTime() / 1000;
                
                // 如果不是第一次运行，且发现订单时间是刚刚的（或晚于上次检查时间）
                if (!isFirstRun && orderTime >= lastCheckTime - 5) {
                    // 并且不是未支付状态（如果接口能查出来）
                    if (topOrder.status == 1 || topOrder.status == '1') {
                        playSound();
                        if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
                        showNotify(topOrder.realmoney || topOrder.money, topOrder.trade_no);
                    }
                }
                
                lastCheckTime = Math.floor(Date.now() / 1000);
                isFirstRun = false;
            }
        });
    }

    // 申请通知权限
    function requestNotify() {
        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission();
        }
        // 激活音频环境
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') audioCtx.resume();
    }

    $(document).ready(function(){
        // 用户第一次点击屏幕时，申请权限并激活音频
        $(document).one('click touchstart', function(){ requestNotify(); });
        
        // 每 3 秒轮询一次，减轻服务器压力（1秒太频繁容易被防火墙拦截）
        setInterval(checkNewOrder, 3000);
    });
})();
</script>
  <div style="text-align:center;color:#999;font-size:12px;padding:20px 0;">
    <?php if(!empty($userrow["footer_text"])){ echo htmlspecialchars($userrow["footer_text"]); } ?>
  </div>
</body>

</html>