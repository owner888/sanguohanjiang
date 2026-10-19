/* movieClip 
var movieclip = new movieClipClass({"img":staticUrl + 'img/wave.png', "width":100, "height":100, "x":0, "y":0, "loop":true,
"frames":[
    {rect:[0,0], interval:60},
    {rect:[100,0], interval:60},
    {rect:[200,0], interval:60},
    {rect:[300,0], interval:60},
    {rect:[0,100], interval:60},
    {rect:[100,100], interval:60},
    {rect:[200,100], interval:60},
    {rect:[300,100], interval:60},
    {rect:[0,200], interval:60},
    {rect:[100,200], interval:60},
    {rect:[200,200], interval:60},
    {rect:[300,200], interval:60},
],
"scaleX":1,
"endDestory":false,//播放结束是否销毁
"zindex":10,
});
movieclip.appendTo($('body'));
movieclip.play();

//利用animate做动画
$(movieclip.obj).animate({
  left: '600px',top:'300px'
}, 1000, 'ease-out');
*/
(function() {
    movieClipClass = function(props) {
        this.playtype = 2; //播放类型 1:transform3d, 2:普通dom
        this.id = '';
        this.img = '';
        this.width = 0;
        this.height = 0;
        this.x = 0;
        this.y = 0;
        this.frames = null;
        this.loop = false;
        this.looptimes = 1;
        this.obj = null;
        this.pause = false;
        this.currentFrame = 0;
        this.callback = null;
        this.scaleX = 0;
        this.scale = 1;//放大缩小

        this.img = props.img;
        this.width = props.width;
        this.height = props.height;
        this.x = props.x;
        this.y = props.y;
        this.frames = props.frames;
        this.loop = props.loop;
        if (props.callback) {
            this.callback = props.callback;
        }
        if (props.scaleX) {
            this.scaleX = props.scaleX;
        }
        if (props.scale != 1) {
            this.scale = props.scale;
        }
        if (props.endDestory) {
            this.endDestory = props.endDestory;
        }
        if (props.zindex) {
            this.zindex = props.zindex;
        }
        this.thread = null;

        this.init();
    };
    movieClipClass.prototype = {

        init: function() {
            this.id = helper.createUUID("mymovieclip");
            var html = '';

            if (this.playtype == 1) {
                html += '<div id="' + this.id + '" style="display:none;pointer-events:none;' + (this.zindex > 0 ? 'z-index:'+this.zindex+';': '') + 'overflow: hidden;width:' + this.width + 'px;height:' + this.height + 'px;position:absolute;left:' + this.x + 'px;top:' + this.y + 'px;' + (this.scaleX == -1 ? '-webkit-transform: scaleX(-1);': '') + (this.scale != 1 ? '-webkit-transform: scale('+this.scale+');': '') + '">';
                html += '<div class="sprite_container"><img src="' + this.img + '" /></div>';
                html += '</div>';
            } else {
                html += '<div id="' + this.id + '" style="display:none;pointer-events:none;background-image:url(' + this.img + ');background-repeat:no-repeat;background-position:0 0;' + (this.zindex > 0 ? 'z-index:'+this.zindex+';': '') + 'width:' + this.width + 'px;height:' + this.height + 'px;position:absolute;left:' + this.x + 'px;top:' + this.y + 'px;' + (this.scaleX == -1 ? '-webkit-transform: scaleX(-1);': '') + (this.scale != 1 ? '-webkit-transform: scale('+this.scale+');': '') + '"></div>';
            }

            this.obj = $(html);
        },

        appendTo: function(dom) {
            dom.append(this.obj);
            dom = null;
        },

        /* 开始播放动画 */
        play: function() {
            this.obj.show();
            this.playTimer(0);
        },

        playTimer: function(currentFrame) {
            var self = this;

            if (self.pause == true) {
                return;
            }

            var frame = self.getFrame(currentFrame);
            /* 2013.3.9 增加支持指定loop次数 */
            if (frame == false && (self.loop === true || (typeof self.loop != 'boolean' && self.looptimes < self.loop))) { //帧不存在(end)，判断是否循环，是的话，重头开始
                self.initAnim();
                currentFrame = 0;
                frame = self.getFrame(currentFrame);
                if(typeof self.loop != 'boolean') {
                    self.looptimes++;
                }
            }

            if (frame) {
                if (self.playtype == 1) {
                    var _style = {
                        '-webkit-transform': 'translate3d(-' + frame.rect[0] + 'px, -' + frame.rect[1] + 'px, 0px) scale(1)'
                    };
                    self.obj.find('.sprite_container').css(_style);
                    if(typeof frame.rect[2] != 'undefined' && typeof frame.rect[3] != 'undefined') {//x,y
                        self.obj.css({left: frame.rect[2], top: frame.rect[3]});
                    }
                } else {
                    var _style = {
                        'background-position': '-' + frame.rect[0] + 'px -' + frame.rect[1] + 'px'
                    };
                    if(typeof frame.rect[2] != 'undefined' && typeof frame.rect[3] != 'undefined') {//x,y
                        _style.left = frame.rect[2];
                        _style.top = frame.rect[3];
                    }
                    self.obj.css(_style);
                }
                self.thread = setTimeout(function() {
                    self.currentFrame = currentFrame + 1;
                    self.playTimer(self.currentFrame);
                },
                frame.interval);
            } else {
                clearTimeout(self.thread);
                self.pause = true;
                if (self.callback) {
                    self.callback();
                    self.callback = null;
                }
                if (self.endDestory == true) {
                    self.endDestory = false;
                    self.destory();
                }
            }
        },
        
        stop: function() {
            if (this.thread != null) {
                clearTimeout(this.thread);
            }
            this.pause = true;
        },

        getFrame: function(index) {
            if (this.frames && this.frames[index] != undefined) {
                return this.frames[index];
            }
            return false;
        },

        initAnim: function() {
            clearTimeout(this.thread);
            this.currentFrame = 0;
            this.pause = false;
        },

        /* 重播 */
        replay: function() {
            this.initAnim();
            this.play();
        },

        /* 销毁动画 */
        destory: function() {
            this.initAnim();
            if(this.obj) {
               this.obj.remove();
                this.img = null;
                this.obj = null; 
            }
        },
    };
})();

// canvas动画
(function () {
    canvasAnimClass = function (props) {
        this.id = '';
        this.canvasOffset = props.canvasOffset; // Obj，canvas的位置尺寸
        this.resource = props.resource || 'all'; // 规定图片资源的两种形式，‘all’：一张图包含所有的帧，‘single’：一张图是一帧
        this.img = props.img || ''; // 只在‘all’模式下才需要传递该参数
        this.obj = null;
        this.context = null;
        this.bufferObj = null;
        this.bufferContext = null;
        this.imgOffset = props.imgOffset || [{left: 0, top: 0}]; // Array，动画的位置和缩放，可以包含多个，例如：[{left: 50, top: 50}, {left: 100, top: 100, scaleX: -1, scaleY: 2}]
        this.width = props.width; // 每一帧的宽度
        this.height = props.height; // 每一帧的高度
        this.bufferWidth = 0;
        this.bufferHeight = 0;
        this.loop = props.loop; // 是否循环
        this.interval = props.interval; // 每帧的间隔时间
        this.anim = props.anim; // 攻击动画帧，数组
        this.timer = null; // 计时器
        this.currentFrame = 0;  // 当前帧
        this.parse = false;
        this.isPlay = false;
        this.zIndex = props.zIndex || 10;

        if (props.loadComplete) {
            this.loadComplete = props.loadComplete;
        }

        if (props.callback) {
            this.callback = props.callback;
        }

        // 动画结束后是否摧毁动画，Boolean值
        if (props.endDestroy) {
            this.endDestroy = props.endDestroy;
        }

        this.init();
    };

    canvasAnimClass.prototype = {

        init: function () {

            var that = this;
            var html = '';
            var bufferHtml = '';
            var animLen = that.anim.length;
            that.canvasOffset.width = (that.canvasOffset.width || that.width);
            that.canvasOffset.height = (that.canvasOffset.height || that.height);

            that.id = helper.createUUID('canvasAnim');
            html = '<canvas id="'+ that.id +'" width="'+ that.canvasOffset.width +'" height="'+ that.canvasOffset.height +'" style="display:none;pointer-events:none;position:absolute;left:'+ that.canvasOffset.left +'px;top:'+ that.canvasOffset.top +'px;z-index:'+ that.zIndex +';"></canvas>';
            bufferHtml = '<canvas id="'+ that.id +'_buffer" width="'+ (that.width * animLen) +'" height="'+ that.height +'" style="display:none;pointer-events:none;position:absolute;"></canvas>';

            that.obj = $(html);
            that.context = that.obj[0].getContext('2d');
            that.bufferObj = $(bufferHtml);
            that.bufferContext = that.bufferObj[0].getContext('2d');

            // 加载图片
            if (that.resource == 'all') {
                that.loadAllImg();
            } else  {
                that.loadSingleImg();
            }

        },

        // 加载一张包含所有帧的图片
        loadAllImg: function () {
            
            var that = this;
            var img = new Image();
            img.src = that.img;

            img.onload = function () {

                that.bufferWidth = this.width;
                that.bufferHeight = this.height;
                that.bufferObj.attr({'width': this.width, 'height': this.height});
                that.bufferContext.drawImage(this, 0, 0);

                if (that.isPlay == true) {
                    that.play();
                } else {
                    that.isPlay = true;
                }
            };

            img.onerror = function () {
                if (that.isPlay == true) {
                    that.play();
                } else {
                    that.isPlay = true;
                }
            };
        },

        // 加载很多图片：一张图片就是一帧
        loadSingleImg: function () {
            
            var that = this;

            var img = new Image();
            img.src = that.anim[that.currentFrame].src;

            img.onload = function () {
                that.bufferContext.drawImage(this, that.width * that.currentFrame, 0);
                that.currentFrame ++;

                if (that.currentFrame >= that.anim.length) {
                    that.currentFrame = 0;

                    if (that.isPlay == true) {
                        that.play();
                    } else {
                        that.isPlay = true;
                    }
                } else {
                    that.loadSingleImg();
                }
            };

            img.onerror = function () {
                that.currentFrame ++;

                if (that.currentFrame >= that.anim.length) {
                    that.currentFrame = 0;

                    if (that.isPlay == true) {
                        that.play();
                    } else {
                        that.isPlay = true;
                    }
                } else {
                    that.loadSingleImg();
                }
            };

            img = null;
        },

        appendTo: function (dom) {

            var that = this;
            dom.append(that.obj);
            dom = null;
        },

        // 初始化动画参数
        initAnim: function () {

            var that = this;
            
            if (that.timer != null) {
                clearTimeout(that.timer);
            }

            that.timer = null;
            that.parse = false;
        },

        play: function () {

            if (this.isPlay == false) {
                this.isPlay = true;
                return false;
            }

            this.initAnim();
            this.obj.show();

            if (this.loadComplete) {
                this.loadComplete();
            }

            this.playAnim();
        },

        // 播放动画
        playAnim: function () {
            
            var that = this;

            if (that.parse) {
                return false;
            }

            if (that.currentFrame < that.anim.length) {

                that.context.clearRect(0, 0, that.canvasOffset.width, that.canvasOffset.height);

                var imgItem = null;
                var item = that.anim[that.currentFrame];

                if (that.resource == 'all') {
                    var width = (item.rect[0] + that.width > that.bufferWidth) ? (that.bufferWidth - item.rect[0]) : that.width;
                    var height = (item.rect[1] + that.height > that.bufferHeight) ? (that.bufferHeight - item.rect[1]) : that.height;
                }

                for (var i = 0, len = that.imgOffset.length; i < len; i++) {

                    imgItem = that.imgOffset[i];

                    if (imgItem.scaleX != null || imgItem.scaleY != null) {

                        that.context.save();
                        that.context.translate(imgItem.left + that.width / 2, imgItem.top + that.height / 2);
                        that.context.scale((imgItem.scaleX || 1), (imgItem.scaleY || 1));

                        if (that.resource == 'single') {
                            that.context.drawImage(that.bufferObj[0], that.width * that.currentFrame, 0, that.width, that.height, - that.width / 2, - that.height / 2, that.width, that.height);
                        } else if (that.resource == 'all' && width > 0 && height > 0) {
                            that.context.drawImage(that.bufferObj[0], item.rect[0], item.rect[1], width, height, - that.width / 2, - that.height / 2, width, height);
                        }

                        that.context.restore();
                    } else {
                        if (that.resource == 'single') {
                            that.context.drawImage(that.bufferObj[0], that.width * that.currentFrame, 0, that.width, that.height, imgItem.left, imgItem.top, that.width, that.height);
                        } else if (that.resource == 'all' && width > 0 && height > 0) {
                            that.context.drawImage(that.bufferObj[0], item.rect[0], item.rect[1], width, height, imgItem.left, imgItem.top, width, height);
                        }
                    }

                }

                imgItem = null;

                if (item.rect[2] != null && item.rect[3] != null) {
                    that.obj.css({'left': item.rect[2] + 'px', 'top': item.rect[3] + 'px'});
                }

                if (item.callback) {
                    item.callback();
                }

                if (that.loop == true && that.currentFrame == that.anim.length - 1) {
                    that.currentFrame = 0;
                } else {
                    that.currentFrame ++;
                }

                that.timer = setTimeout(function () {
                    that.playAnim();
                }, item.interval);

                item = null;

            } else {
                clearTimeout(that.timer);
                that.timer = null;
                that.parse = true;

                if (that.callback) {
                    that.callback();
                    that.callback = null;
                }

                if (that.endDestroy) {
                    that.endDestroy = false;
                    that.destroy();
                }
            }
        },

        // 停止动画
        stop: function () {

            var that = this;
            
            if (that.timer != null) {
                clearTimeout(that.timer);
                that.timer = null;
            }

            that.parse = true;
        },

        // 销毁动画
        destroy: function () {

            var that = this;

            that.initAnim();
            that.anim = null;

            if (that.obj) {
                that.context = null;
                that.obj.remove();
            }

            that.obj = null;

            if (that.bufferObj) {
                that.bufferContext = null;
                that.bufferObj.remove();
            }

            that.bufferObj = null;
        },
    }
})();

/*
var mc = new playFrameAnimClass({
    'css': {
        'background-image': 'url('+ staticUrl +'img/pop/aaaa.png)',
        'left': '50px',
        'top': '50px',
        'height': '100px',
        'width': '100px',
        'z-index': '10'
    },
    'rowNum': 5,
    'colNum': 4,
    'frameNum': 20,
    'interval': 100,
    'loop': false,
    'endDestory': false,
    'callback': function(){},
});
*/
(function() {
    playFrameAnimClass = function(props) {
        this.id = '';
        this.css = (props.css || {});
        this.rowNum = props.rowNum || 0; // 动画帧的行数
        this.colNum =  props.colNum || 0; // 动画帧的列数
        this.frameNum = props.frameNum || 0; // 动画帧的总数
        this.width = 0; // 帧宽度
        this.height = 0; // 帧高度
        this.interval = (props.interval || 100); // 每帧的间隔时间
        this.frames = null;
        this.frameCallNum = (props.frameCallNum || null); // 用于在某一帧调用回调函数
        this.loop = props.loop || false;
        this.looptimes = 1;
        this.obj = null;
        this.pause = false;
        this.currentFrame = 0;
        this.callback = null;
        this.endDestory = true;
        this.frames = props.frames;

        if (props.callback) {
            this.callback = props.callback;
        }

        if (props.endDestory) {
            this.endDestory = props.endDestory;
        }

        this.thread = null;

        this.init();
    };

    playFrameAnimClass.prototype = {

        init: function() {
            this.id = helper.createUUID("mymovieclip");
            var style = 'display:none;position:absolute;pointer-events:none;background-repeat:no-repeat;background-position:0 0;';
            var item = null;

            for (var i in this.css) {
                item = this.css[i];
                style += i + ':' + item + ';';

                if (i == 'width' || i == 'height') {
                    this[i] = parseInt(item);
                }
            }

            var html = '<div id="' + this.id + '" style="'+ style +'"></div>';
            this.obj = $(html);

            // 当没有输入帧时，通过计算生成
            if (this.frames == null) {
                this.frames = [];
                var index = 0;

                for (var i = 0; i < this.rowNum; i++) {
                    
                    for (var j = 0; j < this.colNum; j++) {
                        
                        this.frames.push({
                            rect: [j * this.width, i * this.height],
                            interval: this.interval
                        });

                        index ++;

                        if (index >= this.frameNum) {
                            break;
                        }
                    }
                }
            }
        },

        appendTo: function(dom) {
            dom.append(this.obj);
            dom = null;
        },

        /* 开始播放动画 */
        play: function() {
            this.obj.show();
            this.playTimer(0);
        },

        playTimer: function(currentFrame) {
            var self = this;

            if (self.pause == true) {
                return;
            }

            var frame = self.getFrame(currentFrame);
            /* 2013.3.9 增加支持指定loop次数 */
            if (frame == false && (self.loop === true || (typeof self.loop != 'boolean' && self.looptimes < self.loop))) { //帧不存在(end)，判断是否循环，是的话，重头开始
                self.initAnim();
                currentFrame = 0;
                frame = self.getFrame(currentFrame);
                if(typeof self.loop != 'boolean') {
                    self.looptimes++;
                }
            }

            if (frame) {

                var _style = {
                    'background-position': '-' + frame.rect[0] + 'px -' + frame.rect[1] + 'px'
                };
                if(typeof frame.rect[2] != 'undefined' && typeof frame.rect[3] != 'undefined') {//x,y
                    _style.left = frame.rect[2];
                    _style.top = frame.rect[3];
                }
                self.obj.css(_style);

                if (currentFrame + 1 == self.frameCallNum && self.callback) {
                    self.callback();
                    self.callback = null;
                }

                self.thread = setTimeout(function() {
                    self.currentFrame = currentFrame + 1;
                    self.playTimer(self.currentFrame);
                },
                frame.interval);
            } else {
                clearTimeout(self.thread);
                self.pause = true;

                if (self.frameCallNum == null && self.callback) {
                    self.callback();
                    self.callback = null;
                }
                
                if (self.endDestory == true) {
                    self.endDestory = false;
                    self.destory();
                }
            }
        },
        
        stop: function() {
            if (this.thread != null) {
                clearTimeout(this.thread);
            }
            this.pause = true;
        },

        getFrame: function(index) {
            if (this.frames && this.frames[index] != undefined) {
                return this.frames[index];
            }
            return false;
        },

        initAnim: function() {
            clearTimeout(this.thread);
            this.currentFrame = 0;
            this.pause = false;
        },

        /* 重播 */
        replay: function() {
            this.initAnim();
            this.play();
        },

        /* 销毁动画 */
        destory: function() {
            this.initAnim();
            if(this.obj) {
               this.obj.remove();
                this.obj = null; 
                this.css = null;
            }
        },
    };
})();
