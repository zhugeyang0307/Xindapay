    </div>
    <?php $__m_cur = basename($_SERVER['PHP_SELF']); ?>
    <div class="app-tabbar">
        <a href="/user/" class="app-tab-item <?php echo ($__m_cur=='index.php')?'active':''; ?>"><i class="fa fa-home"></i><span>首页</span></a>
        <a href="/user/order.php" class="app-tab-item <?php echo ($__m_cur=='order.php')?'active':''; ?>"><i class="fa fa-list-ul"></i><span>订单</span></a>
        <a href="/user/qrcode/" class="app-tab-item tab-center <?php echo (strpos($_SERVER['REQUEST_URI'], 'qrcode') !== false)?'active':''; ?>">
            <div class="tab-center-inner"><i class="fa fa-qrcode"></i></div><span class="tab-center-text">收款</span>
        </a>
        <a href="/user/record.php" class="app-tab-item <?php echo ($__m_cur=='record.php')?'active':''; ?>"><i class="fa fa-yen"></i><span>资金</span></a>
        <a href="/user/app_me.php" class="app-tab-item <?php echo ($__m_cur=='app_me.php')?'active':''; ?>"><i class="fa fa-user-o"></i><span>我的</span></a>
    </div>

    <style>
    .app-tabbar {
        position: fixed; bottom: 0; left: 0; right: 0; height: 60px;
        background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(15px);
        box-shadow: 0 -2px 15px rgba(0,0,0,0.05); display: flex; align-items: center;
        z-index: 1050; padding-bottom: env(safe-area-inset-bottom, 0); border-top: 1px solid #eef0f5;
    }
    .app-tab-item {
        flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
        color: #94a3b8; text-decoration: none !important; font-size: 10.5px; font-weight: 600; height: 100%;
    }
    .app-tab-item i { font-size: 22px; margin-bottom: 3px; transition: transform 0.2s; }
    .app-tab-item.active { color: #1677ff; }
    .app-tab-item.active i { transform: scale(1.1); }
    .tab-center { position: relative; justify-content: flex-start; padding-top: 8px; }
    .tab-center-inner {
        width: 50px; height: 50px; background: linear-gradient(135deg, #1677ff, #0d5cd6);
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        color: #fff; box-shadow: 0 4px 12px rgba(22, 119, 255, 0.35);
        position: absolute; top: -18px; left: 50%; transform: translateX(-50%); border: 3px solid #fff;
    }
    .tab-center-inner i { font-size: 24px; margin: 0; color: #fff; }
    .tab-center-text { margin-top: 34px; color: #64748b; }
    </style>
    <script>
    $(document).ready(function(){
        $('.modal').on('show.bs.modal', function () { $('.app-tabbar').hide(); });
        $('.modal').on('hidden.bs.modal', function () { $('.app-tabbar').show(); });
    });
    </script>



<script>
// ===== 订单提示音与系统通知引擎 (带开关) =====
(function(){
    let lastCheckTime = Math.floor(Date.now() / 1000);
    let isFirstRun = true;
    let audioCtx = null;

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

    function checkNewOrder() {
        // 核心：如果没有开启通知开关，则直接返回，不请求接口
        if (localStorage.getItem('notify_allowed') !== 'yes') {
            lastCheckTime = Math.floor(Date.now() / 1000);
            isFirstRun = true;
            return;
        }

        $.ajax({
            type: 'POST',
            url: '/user/ajax2.php?act=orderList',
            data: { limit: 1, offset: 0, order: 'addtime desc' },
            dataType: 'json',
            success: function(res) {
                if (!res || !res.rows || res.rows.length === 0) return;
                let topOrder = res.rows[0];
                let orderTime = new Date(topOrder.addtime).getTime() / 1000;
                
                if (!isFirstRun && orderTime >= lastCheckTime - 5) {
                    if (topOrder.status == 1 || topOrder.status == '1') {
                        playSound();
                        if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
                        if ("Notification" in window && Notification.permission === "granted") {
                            new Notification("💰 新订单到账", {
                                body: "收款金额: ￥" + (topOrder.realmoney || topOrder.money) + "\n订单号: " + topOrder.trade_no,
                                icon: "/assets/img/app-icon.svg",
                                tag: "new-order"
                            });
                        }
                    }
                }
                lastCheckTime = Math.floor(Date.now() / 1000);
                isFirstRun = false;
            }
        });
    }

    $(document).ready(function(){
        setInterval(checkNewOrder, 3000);
    });
})();
</script>
</body>


</html>
