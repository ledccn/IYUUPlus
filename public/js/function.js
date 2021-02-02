/**
 * 根据ID获取DOM元素对象
 * @param {string} id 
 */
function getById(id) {
  return document.getElementById(id);
}

/**
 * 格式化Layui输入框数组
 * @description 适用于input 、checkbox 、 switch
 * @param {string} field    待格式化的字段
 * @param {object} obj      数据对象
 */
function format_input(field, obj) {
  for (let key in obj[field]) {
    let k = field +'['+ key +']';
    obj[k] = obj[field][key];
  }
  return obj;
}

/**
 * 包装的Ajax请求
 * @param {string} url      URL
 * @param {object} fd       表单数据
 * @param {string} msg      成功后提示消息
 * @param {boolean} reload  成功后是否刷新窗口
 */
function ajax(url, fd, msg, reload) {
  let $ = layui.jquery;
  $.ajax({
    url: url,
    type: "POST",
    data: fd,
    success: function (d) {
      console.log(d);
      if (d.ret === 200) {
        layer.msg(msg, function () {
          if(reload) {
            window.location.reload();
          } else {
            return true;
          }
        });
      } else {
        if (d.msg.length > 0) {
          layer.alert(d.msg, {icon: 2, title: '出错提示'});
        } else {
          layer.alert('未知错误，请截图当前界面，然后求助于QQ群：859882209、931954050、924099912！', {icon: 2, title: '出错提示'});
        }
      }
    },
    complete: function () {
    },
    error : function(request) {
      layer.alert('未知错误，请截图当前界面，然后求助于QQ群：859882209、931954050、924099912！', {icon: 2, title: '出错提示'});
    }
  });
}