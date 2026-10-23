/**
 * 信达支付邮件追踪 SDK
 * 用法：在落地页 <head> 里加一行
 *   <script src="https://pay.yunzexinda.com/mail_track/track/sdk.js"></script>
 *
 * 追踪业务事件：
 *   mtTrack.event('purchase', {amount: 199, order: 'ORD123'});
 */
(function () {
  var rid = null;
  try {
    var m = location.search.match(/[?&]mt_rid=([a-f0-9]{32})/i);
    if (m) rid = m[1];
  } catch (e) {}

  if (!rid) {
    // 没有 rid，说明不是邮件渠道进来的，不追踪
    window.mtTrack = { rid: null, event: function () {} };
    return;
  }

  var API = 'https://pay.yunzexinda.com/mail_track/track/report.php';

  function send(type, name, data) {
    var payload = JSON.stringify({
      rid: rid,
      type: type,
      name: name || '',
      data: data || {}
    });
    try {
      if (navigator.sendBeacon) {
        var blob = new Blob([payload], { type: 'application/json' });
        navigator.sendBeacon(API, blob);
      } else {
        var x = new XMLHttpRequest();
        x.open('POST', API, true);
        x.setRequestHeader('Content-Type', 'application/json');
        x.send(payload);
      }
    } catch (e) {}
  }

  // 自动上报落地页访问
  send('landing');

  // 暴露接口
  window.mtTrack = {
    rid: rid,
    event: function (name, data) {
      if (!name) return;
      send('custom', name, data);
    }
  };
})();