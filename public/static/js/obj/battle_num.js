/* 数字
var num = 1000;
var value = "-" + num.toString();
var numBmp = new Num({id:"bloodnum", src:battleR['num'], max:value.length, gap:3});
numBmp.x = 100;
numBmp.y = 100;
numBmp.width = 400;
numBmp.height = 200;
numBmp.setValue(value);
stage.addChild(numBmp);
Q.Tween.to(numBmp, {y:numBmp.y - 50}, {time:800, onComplete:function(tween) {
    tween.target.parent.removeChild(tween.target);
}});
*/
var Num = function(props) {
    this.e = null; //容器
    this.autoAddZero = false;

    props = props || {};
    this.id = props.id || helper.createUUID("Num");
    this.src = props.src;
    this.max = props.max || 1; //字符数
    this.gap = props.gap || 2; //单位长度
    this.height = props.height || 30; //单位高度
    this.addSign = false; //是否显示前面的加减号
    this.x = props.x || 0;
    this.y = props.y || 0;
    this.value = props.value || '-1'; //显示的字符
    this.zindex = props.zindex || 1;
    this.rect = null;
    this.prepareRect();
    this.init();
};
Num.prototype.prepareRect = function() {
    var temp = {};
    for(var i = 0; i <= 10; i++) {
        var key = i;
        if(i == 10) {
            key = "-";
        }
        temp[key] = [i * this.gap, 0, this.gap, this.height];
    }
    this.rect = temp;
};
Num.prototype.init = function() {
    this.e = $('<div id="' + this.id + '" style="width:' + (this.max * this.gap) + 'px;height:' + this.height + 'px;position:absolute;left:' + this.x + 'px;top:' + this.y + 'px;z-index:' + this.zindex + '"></div>');

    var html = '';
    for (var i = 0; i <= this.value.length; i++) {
        var key = this.value[i];
        if (this.rect[key]) {
            var rect = this.rect[key];
            html += '<div style="position:absolute;top:0;left:' + (i * this.gap) + 'px;width:' + this.gap + 'px;height:' + this.height + 'px;background-image:url(' + this.src + ');background-position:-' + rect[0] + 'px -' + rect[1] + 'px;background-repeat:no-repeat;"></div>';
        }
    }
    this.e.append(html);
    html = null;
};
Num.prototype.destory = function() {
    this.e.remove();
    this.e = null;
};