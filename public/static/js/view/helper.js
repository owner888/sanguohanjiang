(function() {
    helperClass = function() {
        this.e = null;
        this.ex = 0;
        this.ey = 0;
        this.ox = 0;
        this.oy = 0;
        this._counter = 0;

        this.textflash_active = false; //文字闪动状态
        this.textflash_queue = []; //文字闪动队列

        this.lang = {};
        this.Tween = {
            Linear: function(t,b,c,d){ return c*t/d + b; },
            Quad: {
                easeIn: function(t,b,c,d){
                    return c*(t/=d)*t + b;
                },
                easeOut: function(t,b,c,d){
                    return -c *(t/=d)*(t-2) + b;
                },
                easeInOut: function(t,b,c,d){
                    if ((t/=d/2) < 1) return c/2*t*t + b;
                    return -c/2 * ((--t)*(t-2) - 1) + b;
                }
            },
            Cubic: {
                easeIn: function(t,b,c,d){
                    return c*(t/=d)*t*t + b;
                },
                easeOut: function(t,b,c,d){
                    return c*((t=t/d-1)*t*t + 1) + b;
                },
                easeInOut: function(t,b,c,d){
                    if ((t/=d/2) < 1) return c/2*t*t*t + b;
                    return c/2*((t-=2)*t*t + 2) + b;
                }
            },
            Quart: {
                easeIn: function(t,b,c,d){
                    return c*(t/=d)*t*t*t + b;
                },
                easeOut: function(t,b,c,d){
                    return -c * ((t=t/d-1)*t*t*t - 1) + b;
                },
                easeInOut: function(t,b,c,d){
                    if ((t/=d/2) < 1) return c/2*t*t*t*t + b;
                    return -c/2 * ((t-=2)*t*t*t - 2) + b;
                }
            },
            Quint: {
                easeIn: function(t,b,c,d){
                    return c*(t/=d)*t*t*t*t + b;
                },
                easeOut: function(t,b,c,d){
                    return c*((t=t/d-1)*t*t*t*t + 1) + b;
                },
                easeInOut: function(t,b,c,d){
                    if ((t/=d/2) < 1) return c/2*t*t*t*t*t + b;
                    return c/2*((t-=2)*t*t*t*t + 2) + b;
                }
            },
            Sine: {
                easeIn: function(t,b,c,d){
                    return -c * Math.cos(t/d * (Math.PI/2)) + c + b;
                },
                easeOut: function(t,b,c,d){
                    return c * Math.sin(t/d * (Math.PI/2)) + b;
                },
                easeInOut: function(t,b,c,d){
                    return -c/2 * (Math.cos(Math.PI*t/d) - 1) + b;
                }
            },
            Expo: {
                easeIn: function(t,b,c,d){
                    return (t==0) ? b : c * Math.pow(2, 10 * (t/d - 1)) + b;
                },
                easeOut: function(t,b,c,d){
                    return (t==d) ? b+c : c * (-Math.pow(2, -10 * t/d) + 1) + b;
                },
                easeInOut: function(t,b,c,d){
                    if (t==0) return b;
                    if (t==d) return b+c;
                    if ((t/=d/2) < 1) return c/2 * Math.pow(2, 10 * (t - 1)) + b;
                    return c/2 * (-Math.pow(2, -10 * --t) + 2) + b;
                }
            },
            Circ: {
                easeIn: function(t,b,c,d){
                    return -c * (Math.sqrt(1 - (t/=d)*t) - 1) + b;
                },
                easeOut: function(t,b,c,d){
                    return c * Math.sqrt(1 - (t=t/d-1)*t) + b;
                },
                easeInOut: function(t,b,c,d){
                    if ((t/=d/2) < 1) return -c/2 * (Math.sqrt(1 - t*t) - 1) + b;
                    return c/2 * (Math.sqrt(1 - (t-=2)*t) + 1) + b;
                }
            },
            Elastic: {
                easeIn: function(t,b,c,d,a,p){
                    if (t==0) return b;  if ((t/=d)==1) return b+c;  if (!p) p=d*.3;
                    if (!a || a < Math.abs(c)) { a=c; var s=p/4; }
                    else var s = p/(2*Math.PI) * Math.asin (c/a);
                    return -(a*Math.pow(2,10*(t-=1)) * Math.sin( (t*d-s)*(2*Math.PI)/p )) + b;
                },
                easeOut: function(t,b,c,d,a,p){
                    if (t==0) return b;  if ((t/=d)==1) return b+c;  if (!p) p=d*.3;
                    if (!a || a < Math.abs(c)) { a=c; var s=p/4; }
                    else var s = p/(2*Math.PI) * Math.asin (c/a);
                    return (a*Math.pow(2,-10*t) * Math.sin( (t*d-s)*(2*Math.PI)/p ) + c + b);
                },
                easeInOut: function(t,b,c,d,a,p){
                    if (t==0) return b;  if ((t/=d/2)==2) return b+c;  if (!p) p=d*(.3*1.5);
                    if (!a || a < Math.abs(c)) { a=c; var s=p/4; }
                    else var s = p/(2*Math.PI) * Math.asin (c/a);
                    if (t < 1) return -.5*(a*Math.pow(2,10*(t-=1)) * Math.sin( (t*d-s)*(2*Math.PI)/p )) + b;
                    return a*Math.pow(2,-10*(t-=1)) * Math.sin( (t*d-s)*(2*Math.PI)/p )*.5 + c + b;
                }
            },
            Back: {
                easeIn: function(t,b,c,d,s){
                    if (s == undefined) s = 1.70158;
                    return c*(t/=d)*t*((s+1)*t - s) + b;
                },
                easeOut: function(t,b,c,d,s){
                    if (s == undefined) s = 1.70158;
                    return c*((t=t/d-1)*t*((s+1)*t + s) + 1) + b;
                },
                easeInOut: function(t,b,c,d,s){
                    if (s == undefined) s = 1.70158; 
                    if ((t/=d/2) < 1) return c/2*(t*t*(((s*=(1.525))+1)*t - s)) + b;
                    return c/2*((t-=2)*t*(((s*=(1.525))+1)*t + s) + 2) + b;
                }
            },
            Bounce: {
                easeIn: function(t,b,c,d){
                    return c - Tween.Bounce.easeOut(d-t, 0, c, d) + b;
                },
                easeOut: function(t,b,c,d){
                    if ((t/=d) < (1/2.75)) {
                        return c*(7.5625*t*t) + b;
                    } else if (t < (2/2.75)) {
                        return c*(7.5625*(t-=(1.5/2.75))*t + .75) + b;
                    } else if (t < (2.5/2.75)) {
                        return c*(7.5625*(t-=(2.25/2.75))*t + .9375) + b;
                    } else {
                        return c*(7.5625*(t-=(2.625/2.75))*t + .984375) + b;
                    }
                },
                easeInOut: function(t,b,c,d){
                    if (t < d/2) return Tween.Bounce.easeIn(t*2, 0, c, d) * .5 + b;
                    else return Tween.Bounce.easeOut(t*2-d, 0, c, d) * .5 + c*.5 + b;
                }
            }
        };
    };
    helperClass.prototype = {

        //比较数组是否相同
        compArray: function(array1, array2) {
            if ((array1 && typeof array1 === "object" && array1.constructor === Array) && (array2 && typeof array2 === "object" && array2.constructor === Array)) {
                if (array1.length == array2.length) {
                    for (var i = 0; i < array1.length; i++) {
                        var ggg = this.compObj(array1[i], array2[i]);
                        if (!ggg) {
                            return false;
                        }

                    }

                } else {
                    return false;
                }
            } else {
                throw new Error("argunment is  error ;");
            }

            return true;
        },

        //比较两个对象是否相等，不包含原形上的属性计较
        compObj: function(obj1, obj2) {
            if ((obj1 && typeof obj1 === "object") && ((obj2 && typeof obj2 === "object"))) {
                var count1 = this.propertyLength(obj1);
                var count2 = this.propertyLength(obj2);
                if (count1 == count2) {
                    for (var ob in obj1) {
                        if (obj1.hasOwnProperty(ob) && obj2.hasOwnProperty(ob)) {

                            if (obj1[ob].constructor == Array && obj2[ob].constructor == Array) //如果属性是数组
                            {
                                if (!this.compArray(obj1[ob], obj2[ob])) {
                                    return false;
                                };
                            } else if (typeof obj1[ob] === "string" && typeof obj2[ob] === "string") //纯属性
                            {
                                if (obj1[ob] !== obj2[ob]) {
                                    return false;
                                }
                            } else if (typeof obj1[ob] === "object" && typeof obj2[ob] === "object") //属性是对象
                            {
                                if (!this.compObj(obj1[ob], obj2[ob])) {
                                    return false;
                                };
                            } else {
                                return false;
                            }
                        } else {
                            return false;
                        }
                    }
                } else {
                    return false;
                }
            }

            return true;
        },

        //获得对象上的属性个数，不包含对象原形上的属性
        propertyLength: function(obj) {
            var count = 0;
            if (obj && typeof obj === "object") {
                for (var ooo in obj) {
                    if (obj.hasOwnProperty(ooo)) {
                        count++;
                    }
                }
                return count;
            } else {
                throw new Error("argunment can not be null;");
            }

        },

        //添加
        textflash_add: function(e, className, time, times, callback) {
            if (!e || e.length <= 0) {
                return;
            }
            this.textflash_queue.push({
                'e': e,
                'className': className,
                'time': time,
                'times': times,
                'callback': callback
            });
            this.textflash_run();
        },

        //运行
        textflash_run: function() {
            if (!this.textflash_active && this.textflash_queue.length > 0) {
                var that = this;
                var task = this.textflash_queue.shift(); //取最早插入的一条
                this.textflash_active = true;

                var i = 0;
                var t = 0;
                var auto = null;

                var _nowclass = task.e.attr('class');
                if (_nowclass == null) {
                    _nowclass = '';
                }
                if (_nowclass != '' && _nowclass.indexOf('textflash') != -1) { //正在运行，返回
                    console.log('正在运行，返回');
                    return false;
                } else {
                    task.e.addClass('textflash');
                }

                auto = setInterval(function() {
                    i != 1 ? i += 1 : i = 0;
                    t++;
                    task.e.attr({
                        'class': _nowclass + ' textflash ' + task.className + i
                    });
                    if (t == task.times) {
                        clearInterval(auto);
                        task.e.attr('class', _nowclass);

                        that.textflash_callback(task.callback);
                        that = i = t = auto = _nowclass = task = null;
                        return;
                    }
                },
                task.time);
            }
        },

        //回调处理
        textflash_callback: function(callback) {
            callback && callback();
            this.textflash_active = false;
            this.textflash_run();
        },

        getTalentColor: function(quality) {
            quality = parseInt(quality);
            var qualitytype = '';

            switch (quality) {
            case 1:
                qualitytype = lang('white');
                break;
            case 2:
                qualitytype = lang('green');
                break;
            case 3:
                qualitytype = lang('blue');
                break;
            case 4:
                qualitytype = lang('golden');
                break;
            case 5:
                qualitytype = lang('purple');
                break;
            case 6:
                qualitytype = lang('talent_red');
                break;
            case 7:
                qualitytype = lang('orange');
                break;
            }

            return qualitytype;
        },

        getTalent: function(type, simple) {
            type = parseInt(type);
            var talentname = '';

            switch (type) {
            case 1:
                talentname = simple == true ? lang('p_atk') : lang('p_attack');
                break;
            case 2:
                talentname = simple == true ? lang('p_def') : lang('p_defense');
                break;
            case 3:
                talentname = simple == true ? lang('m_atk') : lang('m_attack');
                break;
            case 4:
                talentname = simple == true ? lang('m_def') : lang('m_defense');
                break;
            case 5:
                talentname = lang('life');
                break;
            }

            return talentname;
        },

        /* return:   王<br/>二<br/>小 */
        getBrName: function(name) {
            var newname = '';
            for (var i = 0; i < name.length; i++) {
                if (i > 0) {
                    newname += '<br/>' + name[i];
                } else {
                    newname += name[i];
                }
            }
            return newname;
        },

        formatDate: function(s, showhour) {
            if (!s) {
                s = '-';
            }
            return new Date().getFullYear() + s + (new Date().getMonth() + 1) + s + new Date().getDate() + (showhour == true ? s + new Date().getHours() : '');
        },

        getBattleZindex: function(pos, isattack) {
            var zindex = 1;

            if (pos == 1 || pos == 4 || pos == 7) {
                zindex = isattack ? 11 : 10;
            } else if (pos == 2 || pos == 5 || pos == 8) {
                zindex = isattack ? 13 : 12;
            } else {
                zindex = isattack ? 15 : 14;
            }

            return zindex;
        },

        getLevelRewardType: function(type) {
            type = parseInt(type);
            var result = '';

            switch (type) {
            case 1:
                result = lang('gold');
                break;
            case 2:
                result = lang('silver');
                break;
            case 3:
                result = lang('act');
                break;
            case 4:
                result = lang('blue_jade');
                break;
            case 5:
                result = lang('yellow_jade');
                break;
            case 6:
                result = lang('purple_jade');
                break;
            }

            return result;
        },

        /* 获取随机tips */
        getRandomTips: function() {
            var tips = [lang('tips1'), lang('tips2'), lang('tips3'), lang('tips4'), lang('tips5'), lang('tips6'), lang('tips7'), lang('tips8'), lang('tips9'), lang('tips10'), lang('tips11'), lang('tips12'), lang('tips13'), lang('tips14'), lang('tips15'), lang('tips16'), lang('tips17'), lang('tips18'), lang('tips19'), lang('tips20'), lang('tips21'), lang('tips22'), lang('tips23'), lang('tips24'), lang('tips25'), lang('tips26')];
            var index = this.getRandom(0, tips.length);
            return tips[index];
        },
        /**
         * 根据指定名字生成一个全局唯一的ID，如Stage1，Bitmap2等。
         */
        createUUID: function(name) {
            var charCode = name.charCodeAt(name.length - 1);
            if (charCode >= 48 && charCode <= 57) name += "_";
            return name + this._counter++;
        },

        urlParams: function() {
            var params = {};
            var url = window.location.href;
            var idx = url.indexOf("?");
            if (idx > 0) {
                var queryStr = url.substring(idx + 1);
                var args = queryStr.split("&");
                for (var i = 0,
                a, nv; a = args[i]; i++) {
                    nv = args[i] = a.split("=");
                    params[nv[0]] = nv.length > 1 ? nv[1] : true;
                }
            }
            return params;
        },

        /* 随机1-n的一个数 */
        getRandom: function(min, max) {
            var range = max - min;
            var rand = Math.random();
            return (min + Math.round(rand * range));
        },

        str_repeat: function(s, n) {
            var string = '';
            for (var i = 0; i < n; i++) {
                string += s;
            }
            return string;
        },

        /* 取最后一个数据 */
        array_end_key: function(array) {
            var key = null;

            for (var i in array) {
                key = i;
            }

            return key;
        },

        /* 取数组第一个数 */
        array_first: function(array) {
            var data = null;

            for (var i in array) {
                data = array[i];
                if (data != null) {
                    break;
                }
            }

            return data;
        },

        max: function(a, b) {
            return a > b ? a: b;
        },

        min: function(a, b) {
            return a < b ? a: b;
        },

        getEnemyType: function(type) {
            var typename = '';
            if (type == 1) {
                typename = lang('normal');
            } else if (type == 2) {
                typename = lang('elite');
            } else if (type == 3) {
                typename = lang('captain');
            } else if (type == 4) {
                typename = lang('king');
            }
            return typename;
        },

        toMove: function(id) {
            var that = this;

            that.e = $('#' + id);
            that.e.css({
                'z-index': 100
            });
            document.getElementById(id).addEventListener('touchstart',
            function(event) {
                console.log(that.e);
                that.ex = parseInt(that.e.css('left').replace('px', ''));
                that.ey = parseInt(that.e.css('top').replace('px', ''));

                that.ox = parseInt(event.touches[0].pageX);
                that.oy = parseInt(event.touches[0].pageY);

                that.e.css({
                    'box-shadow': '1px 1px 1px #000000'
                });

            },
            false);

            document.getElementById(id).addEventListener('touchend',
            function(event) {

                that.e.css({
                    'z-index': 0
                });
                that.ox = 0;
                that.oy = 0;
                that.ex = 0;
                that.ey = 0;

                that.e.css({
                    'box-shadow': 'none'
                });

            },
            false);

            document.getElementById(id).addEventListener('touchmove',
            function(event) {
                var nX = parseInt(event.touches[0].pageX);
                var nY = parseInt(event.touches[0].pageY);

                var newX = nX - that.ox;
                var newY = nY - that.oy;
                var realX = that.ex + newX;
                var realY = that.ey + newY;
                that.e.css({
                    left: realX + 'px'
                });
                that.e.css({
                    top: realY + 'px'
                });
                //console.log('x:' + ex + ', y:' + ey);
            },
            false);
        },

        /* 秒数转成 x小时x分钟x秒 */
        timeToString: function(timeseconds, sp) {

            timeseconds = parseInt(timeseconds);
            //少于一分钟

            if (sp) {
                if (timeseconds < 60) {
                    return timeseconds + lang('s');
                }
            }

            if (timeseconds < 60) {
                return this.zeroPad(timeseconds, 2) + lang('s');
            }

            var secs = timeseconds % 60; //秒
            var mins = (timeseconds - secs) / 60; //分

            if (sp) {
                if (mins < 60) {
                    return mins + lang('mins');
                }
            }

            if (mins < 60) { //少于一小时
                return mins + lang('min') + this.zeroPad(secs, 2) + lang('s');
            }

            //大于一小时
            var mins_new = mins % 60; //分
            var hous = (mins - mins_new) / 60; //小时

            /*只返回最接近的最大时间*/
            if (sp) {
                if (hous > 24) {
                    return Math.floor(hous / 24) + lang('day');
                } else {
                    return hous + lang('hour');
                }
            }

            if (hous <= 24) {
                return hous + lang('hour') + mins_new + lang('min') + secs + lang('s');
            } else {
                return Math.floor(hous / 24) + lang('day') + ' ' + hous % 24 + lang('hour') + mins_new + lang('min') + secs + lang('s');
            }
        },

        /* 描述转成 00:00 */
        timeToClock: function(timeseconds) {

            timeseconds = parseInt(timeseconds);
            //少于一分钟
            if (timeseconds < 60) {
                return '00:' + this.zeroPad(timeseconds, 2);
            }

            var secs = timeseconds % 60; //秒
            var mins = (timeseconds - secs) / 60; //分
            if (mins < 60) { //少于一小时
                return this.zeroPad(mins, 2) + ':' + this.zeroPad(secs, 2);
                //return mins + '分' + secs + '秒';
            }

            //大于一小时
            var mins_new = mins % 60; //分

            var hous = (mins - mins_new) / 60; //小时
            //return hous + '小时' + mins_new + '分' + secs + '秒';

            if (hous <= 24) {
                return this.zeroPad(hous, 2) + ':' + this.zeroPad(mins_new, 2) + ':' + this.zeroPad(secs, 2);
            } else {
                return Math.floor(hous / 24) + lang('day') + ' ' + this.zeroPad(hous % 24, 2) + ':' + this.zeroPad(mins_new, 2) + ':' + this.zeroPad(secs, 2);
            }
        },

        /* 文字补零 */
        zeroPad: function(num, n) {
            var len = num.toString().length;
            while (len < n) {
                num = "0" + num;
                len++;
            }
            return num;
        },

        /* 获取装备颜色 */
        getItemColor: function(quality) {
            quality = parseInt(quality);
            var color = '#FFFFFF';
            switch (quality) {
            case 2:
                //绿
                color = '#09964c';
                break;
            case 3:
                //蓝
                color = '#1262bc';
                break;
            case 4:
                //金
                color = '#d6a600';
                break;
            case 5:
                //紫
                color = '#8d00a2';
                break;
            case 6:
                //暗红色
                color = '#c50000';
                break;
            case 7:
                //橙色
                color = '#f47920';
                break;
            }
            return color;
        },
        
        getHeroColor: function(quality) {
            return this.getItemColor(quality);
        },
        
        /* 获取装备所需职业名字 */
        getItemVocation: function(type) {
            if (type == 1) {
                return lang('reggie');
            } else if (type == 2) {
                return lang('counselors');
            } else {
                return lang('universal');
            }
        },

        /* 获取装备增加的能力名字 */
        getItemPowerName: function(type) {
            var name = '';
            type = parseInt(type);
            switch (type) {
            case 1:
                name = lang('p_attack');
                break;
            case 2:
                name = lang('m_attack');
                break;
            case 3:
                name = lang('p_defense');
                break;
            case 4:
                name = lang('m_defense');
                break;
            case 5:
                name = lang('life_num');
                break;
            }
            return name;
        },

        /* 获取阵法增加的能力名字 
            阵法类型
            5  所有防御提升X%
            6  所有攻击提升X%
            7  所有攻击防御同时提升X%

        */
        getPowerName: function(type) {
            var name = '';
            type = parseInt(type);
            switch (type) {
            case 1:
                name = lang('p_attack');
                break;
            case 2:
                name = lang('p_defense');
                break;
            case 3:
                name = lang('m_attack');
                break;
            case 4:
                name = lang('m_defense');
                break;
            case 5:
                name = lang('teampop_all_defense');
                break;
            case 6:
                name = lang('teampop_all_atk');
                break;
            case 7:
                name = lang('teampop_all_def&atk');
                break;
            }
            return name;
        },

        /*获取阵法名字*/
        getNameByMatrixId: function(id) {
            var name = '';
            switch (parseInt(id)) {
            case 1:
                name = lang('team1');
                break;
            case 2:
                name = lang('team2');
                break;
            case 3:
                name = lang('team3');
                break;
            case 4:
                name = lang('team4');
                break;
            case 5:
                name = lang('team5');
                break;
            case 6:
                name = lang('team6');
                break;
            case 7:
                name = lang('team7');
                break;
            case 8:
                name = lang('team8');
                break;
            }
            return name;
        },

        /*验证昵称*/
        checkNickName: function(nickName) {
            if (nickName == '') {
                xGame.toast.show(lang('nickname_not_null'));
                return false;
            }
            if (nickName.length < 2 || nickName.length > 20) {
                xGame.toast.show(lang('nickname_2_20'));
                return false;
            }
            /*
            var pattern = new RegExp("^[a-zA-Z0-9_\u4e00-\u9fa5]+$"); /*中文，字母，数字，不限位置的下划线*
            var result = pattern.test(nickName);
            if(result == false){
                xGame.toast.show('昵称只能由数字，汉字，下划线组成');
            }
            return result;
            */
            return true;
        },

        /*对象属性排序
         *data.sort(compare("name"));
         * 现在是从小到大排列
         */
        compare: function(propertyName) {
            return function(object1, object2) {
                var value1 = object1[propertyName];
                var value2 = object2[propertyName];
                if (value2 > value1) {
                    return - 1;
                } else if (value2 < value1) {
                    return 1;
                } else {
                    return 0;
                }
            }
        },

        getItemTypeName: function(type) {
            var name = '';
            switch (type) {
            case 1:
                name = lang('reggie_weapons');
                break;
            case 2:
                name = lang('counselors_weapons');
                break;
            case 3:
                name = lang('armor');
                break;
            case 4:
                name = lang('cloak');
                break;
            case 5:
                name = lang('bingfu');
                break;
            }
            return name;
        },

        getSoulTypeName: function(type) {
            var name = '';
            switch (type) {
            case 1:
                name = lang('shiqi');
                break;
            case 2:
                name = lang('wuli');
                break;
            case 3:
                name = lang('zhili');
                break;
            case 4:
                name = lang('life');
                break;
            case 5:
                name = lang('miss');
                break;
            case 6:
                name = lang('shipo');
                break;
            case 7:
                name = lang('crit_rate');
                break;
            case 8:
                name = lang('crit_damage');
                break;
            case 9:
                name = lang('mingzhong');
                break;
            case 10:
                name = lang('jingzhun');
                break;
            case 11:
                name = lang('kangbao');
                break;
            }
            return name;
        },
	
        //获得武将图鉴名称
        getBookTypeName: function(type){
            var name = '';
            switch (parseInt(type)) {
                case 1:
                    name = lang('wuli');//武力
                    break;
                case 2:
                    name = lang('zhili');//智力
                    break;
                case 3:
                    name = lang('tili');//体力
                    break;
                case 4:
                    name = lang('p_attack');//物攻
                    break;
                case 5:
                    name = lang('p_defense');//物防
                    break;
                case 6:
                    name = lang('m_attack');//魔攻
                    break;
                case 7:
                    name = lang('m_defense');//魔防
                    break;
                case 8:
                    name = lang('life_num');//生命
                    break;
                case 9:
                    name = lang('miss');//闪避
                    break;
                case 10:
                    name = lang('crit_rate');//暴击率
                    break;
                case 11:
                    name = lang('shipo');//识破
                    break;
                case 12:
                    name = lang('chushishiqi');//初始士气
                    break;
                case 13:
                    name = lang('mingzhong');//初始士气
                    break;
                case 14:
                    name = lang('jingzhun');//初始士气
                    break;
            }
            return name;
        },
        
        //获得武将图鉴名称
        getQualityName: function(type){
            var name = '';
            switch (parseInt(type)) {
                case 1:
                    name = lang('normal');
                    break;
                case 2:
                    name = lang('strengthen');
                    break;
                case 3:
                    name = lang('advanced');
                    break;
                case 4:
                    name = lang('whitegold');
                    break;
                case 5:
                    name = lang('extreme');
                    break;
                case 6:
                    name = lang('myth');
                    break;
            }
            return name;
        },

        /*用户名验证*/
        checkUserName: function(u) {
            var pattern = new RegExp("^[A-Za-z0-9]+$");
            if (u == '') {
                xGame.toast.show(lang('account_null'));
                return false;
            } else if (u.length < 4 || u.length > 16 || !pattern.test(u)) {
                xGame.toast.show(lang('account_rule'));
                return false;
            } else {
                return true;
            }
        },

        /*密码验证*/
        checkPassWord: function(p) {
            var pattern = new RegExp("^[A-Za-z0-9]+$");
            if (p == '') {
                xGame.toast.show(lang('password_not_null'));
                return false;
            } else if (p.length < 6 || p.length > 16 || !pattern.test(p)) {
                xGame.toast.show(lang('password_rule'));
                return false;
            } else {
                return true;
            }
        },

        /*手机号验证*/
        checkTel: function(t) {
            if(t == '' || _inArray([3, 100, 153, 154], channelID)) {
                return true;
            }

            if(/^1[3|4|5|8][0-9]\d{8}$/.test(t)) {
                return true;
            } else {
                xGame.toast.show(lang('tel_rule'));
                return false;
            }
        },

        /*中文英文数字下划线，下划线位置不限*/
        checkStr: function(str) {
            return true;// 由于多语言版本的存在，暂时不做检查
            /*
            var pattern = new RegExp("^[a-zA-Z0-9_\u4e00-\u9fa5]+$");
            if (pattern.test(str)) {
                return true;
            } else {
                return false;
            }*/
        },

        /* 战斗舞台宽布局适配：战斗按 1024x768 设计（背景图原生尺寸），交互舞台
           （标准/精英战斗 960x540、国战 560x540）按居中绝对定位。宽视口
           （--hj-vw>1024：桌面宽屏 zoom 布局、手机横屏）下页面背景已
           background-size:100% 100% 铺满拉伸，舞台须按同比例 scale
           （原点底部中心 → 保持居中+贴底），精灵才能与背景美术保持原版相对位置；
           窄视口（vw<1024）下收缩舞台，避免精灵跑到屏幕外。
           --hj-vw 由 index.html 写入实际布局视口宽（设计坐标系）。
           视口变化（resize/横竖屏，index.html fit() 会派发 resize）时重应用，
           保证战斗页/结算界面停留期间缩放比始终跟随。 */
        battleStageScale: function() {
            var sels = ['#container', '#eliteworld_page #battlefield', '#cbattleworld_page #battlefield'];
            var vw = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--hj-vw'));
            if (!(vw > 0)) { return; }
            var s = vw / 1024;
            for (var i = 0; i < sels.length; i++) {
                var el = $(sels[i]);
                if (el.length > 0) {
                    el.css({
                        'transform': 'scale(' + s + ')',
                        '-webkit-transform': 'scale(' + s + ')',
                        'transform-origin': 'bottom center',
                        '-webkit-transform-origin': 'bottom center'
                    });
                }
            }
        },

        /*战斗小弹窗*/
        creatBox: function() {
            var that = this;
            var html = "";

            var arr = ['left', 'right'];
            for (var i = 0; i < arr.length; i++) {
                var team = battle_pos[arr[i]];
                for (var ii in team) {
                    var info = team[ii];
                    html += '<div type="' + arr[i] + '" pos="' + ii + '" class="groundbox" style="z-index:100;width:80px;height:100px;position:absolute;top:' + (info.y - 100) + 'px;left:' + (info.x - 40) + 'px;"></div>';
                }
            }

            var attack;
            $("#container").append(html);
            $("#container").undelegate('.groundbox', 'touchstart').delegate('.groundbox', 'touchstart',
            function() {
                var pos = $(this).attr('pos');
                var type = $(this).attr('type');
                //判断该位置上的值是否为空
                if (sprites[type][pos] != undefined) {
                    attack = $($.template('attackpop_tmpl'));
                    $('#container').append(attack);
                    //var site = $(this).offset();
                    var site = {
                        left: battle_pos[type][pos].x,
                        top: battle_pos[type][pos].y
                    };
                    var width = sprites[type][pos].blood == 0 ? 0 : sprites[type][pos].blood / sprites[type][pos].allblood * 124;
                    var rage = sprites[type][pos].power == 0 ? 0 : sprites[type][pos].power / 100 * 124;
                    if (type == 'left') {
                        attack.css({
                            'top': site.top - 114,
                            'left': site.left + 50
                        });
                    } else {
                        attack.css({
                            'top': site.top - 114,
                            'left': site.left - 350
                        });
                    }

                    //attack.find('.img').css({'background':'url('+ staticUrl +'img/avatar/'+ sprites[type][pos].herodata.image +'.png) 0 0 no-repeat'});
                    attack.find('.img').css({
                        'background': 'url(' + xGame.avatar(sprites[type][pos].herodata.image) + ') 0 0 no-repeat'
                    });
                    attack.find('.name').html(sprites[type][pos].name);
                    attack.find('.blood').find('em').css({
                        'width': width
                    });
                    attack.find('.rage').find('em').css({
                        'width': rage
                    });
                    attack.find('.level').html('Lv.' + sprites[type][pos].herodata.level);
                    attack.find('.skill').html(lang('skill') + '：' + sprites[type][pos].herodata.skill);
                    attack.show();
                    /*
                    attack.bind('touchend', function() {
                        attack.unbind();
                        attack.remove();
                        attack = null;
                    });
                    console.log(sprites[type][pos]);
                    */
                    //取模版
                } else {
                    console.log(lang('site_no_body'));
                }

            }).undelegate('.groundbox', 'touchend').delegate('.groundbox', 'touchend',
            function() {
                if (attack != null) {
                    attack.remove();
                    attack = null;
                }
            });
        },

        /*语言包转换 （要转换的字符，[字符中参数1,字符中参数2,...]）*/
        lang: function(key, param) {
            var str = '';

            //该数组引用语言包内容
            /*var lang = {
                "I'm here" : "我在这里",
                "滚开" : "{1} go away"
            };*/

            if (typeof lang[key] != 'undefined') {
                str = lang[key];
                if (typeof param != 'undefined') {
                    for (var i = 0; i < param.length; i++) {
                        str = str.replace('{' + (i + 1) + '}', param[i]);
                    }
                }
            }
            return str;
        },
        
        //根据马的id设置position，一行是6个
        getHorsePostion: function(type, pos) {
            if (pos == 100) {//年兽，特殊处理一下
                pos = 23;
            } else if (pos == 101) { // 经验银马
                pos = 24;
            } else if (pos == 102) { // 经验金马
                pos = 25;
            } else if (pos == 103) { // 比翼鸟
                pos = 26;
            } else if (pos == 104) { // 天禄
                pos = 27;
            } else if (pos == 105) { // 远古龙鹰
                pos = 28;
            } else if (pos == 106) { // 魔法扫帚
                pos = 29;
            }
            var x = 0;
            var y = 0;
            var w = 0;
            var h = 0;
            if (type == 'big') {
                w = 170;
                h = 184;
            } else if (type == 'small') {
                w = 100;
                h = 100;
            } else if (type == 'buff') {
                w = 45;
                h = 40;
            }
            x = (pos - 1) % 6 * w * -1;
            y = (Math.ceil(pos / 6) - 1) * h * -1;
            
            return x + 'px ' + y + 'px';
        },

        /* 返回聊天信息里的纯中文(用于排重) */
        chatClean: function(str) {
            var reg = /[^\u4E00-\u9FA5\uf900-\ufa2d]/g; //匹配非中文的正则表达式 
            return str.replace(reg, ''); 
        },

        // 用法示例
        // helper.animate(dom, {'left': '20px', 'width': '300px'}, 1000, 'Back.easeOut', function(){})
        animate: function(dom, style, time, type, callback) {
            var startSyle = []; // 初始样式
            var endStyle = [];  // 目标样式
            var styleName = []; // 样式名
            var changeStyle = [];   // 样式的变化量
            var step = 0;
            var sumStep = Math.ceil(parseFloat(time) / 20);   // 总步数
            var length = 0;
            var domLen = dom.length;
            var timer = null;
            var objName = type.split('.');
            var tweenFun = null;

            // 找到对应的Tween算法
            if (type == 'Linear') {
                tweenFun = this.Tween.Linear;
            } else {
                tweenFun = this.Tween[objName[0]][objName[1]];
            }

            var domStyle = [];

            // 获取起始样式和终止样式
            for (var i in style) {
                styleName.push(i);

                for (var j = 0; j < domLen; j++) {
                    domStyle.push(parseFloat($(dom[j]).css(i)));
                }
                startSyle.push(domStyle);
                domStyle = [];
                endStyle.push(parseFloat(style[i]));
            }

            length = startSyle.length;

            for (var i = 0; i < length; i++) {

                for (var j = 0; j < domLen; j++) {
                    domStyle.push(endStyle[i] - startSyle[i][j]);
                }
                changeStyle.push(domStyle);
                domStyle = [];
            }

            domStyle = null;

            function clear() {
                timer = null;
                startSyle = null;
                endStyle = null;
                changeStyle = null;
                styleName = null;
                objName = null;
                tweenFun = null;
                dom = null;
                style = null;
                callback = null;
            }

            function loop() {
                
                for (var i = 0; i < length; i++) {

                    for (var j = 0; j < domLen; j++) {
                        $(dom[j]).css(styleName[i], tweenFun(step, startSyle[i][j], changeStyle[i][j], sumStep).toFixed(2) + 'px');
                    }

                }

                step ++;

                if (step < sumStep) {
                    timer = setTimeout(loop, 20);
                } else {
                    clearTimeout(timer);

                    for (var i = 0; i < length; i++) {
                        dom.css(styleName[i], endStyle[i] + 'px');
                    }

                    if (callback) {
                        callback();
                    }

                    clear();
                }
            }

            loop();
        },

        // transform变化动画
        // helper.animate3D(dom, [2, 0, 0, 2, 300, 500], 1000, 'Back.easeOut', function(){})
        animate3D: function(dom, style, time, type, callback) {
            var startSyle = [];
            var endStyle = style;
            var changeStyle = [];
            var resultStyle = [];
            var step = 0;
            var sumStep = Math.ceil(parseInt(time) / 20);   // 总步数
            var length = 6;
            var domLen = dom.length;
            var timer = null;
            var objName = type.split('.');
            var tweenFun = null;

            // 找到对应的Tween算法
            if (type == 'Linear') {
                tweenFun = this.Tween.Linear;
            } else {
                tweenFun = this.Tween[objName[0]][objName[1]];
            }

            // 获取起始样式
            var nowStyle = null;
            var matrix = [];
            for (var i = 0; i < domLen; i++) {
                nowStyle = $(dom[i]).css('-webkit-transform');

                if (nowStyle != 'none') {
                    nowStyle = nowStyle.slice(7, -1);
                    nowStyle = nowStyle.split(',');

                    for (var j = 0; j < length; j++) {
                        matrix[j] = parseFloat(nowStyle[j]);
                    }

                    startSyle.push(matrix);
                    matrix = [];
                } else {
                    startSyle.push([1, 0, 0, 1, 0, 0]);
                }
            }

            // 获取终止样式和变化样式
            for (var i = 0; i < domLen; i++) {

                for (var j = 0; j < length; j++) {
                    matrix[j] = endStyle[j] - startSyle[i][j];
                }

                changeStyle.push(matrix);
                matrix = [];
            }

            matrix = null;

            function clear() {
                timer = null;
                startSyle = null;
                endStyle = null;
                changeStyle = null;
                resultStyle = null;
                nowStyle = null;
                objName = null;
                tweenFun = null;
                dom = null;
                style = null;
                callback = null;
            }

            function loop() {

                for (var i = 0; i < domLen; i++) {
                    for (var j = 0; j < length; j++) {

                        if (changeStyle[i][j] == 0) {
                            resultStyle[j] = startSyle[i][j];
                        } else {
                            resultStyle[j] = tweenFun(step, startSyle[i][j], changeStyle[i][j], sumStep).toFixed(4);
                        }
                    }

                    $(dom[i]).css('-webkit-transform', 'matrix(' + resultStyle.toString() + ')');
                    resultStyle = [];
                }

                step ++;

                if (step < sumStep) {
                    timer = setTimeout(loop, 20);
                } else {
                    clearTimeout(timer);

                    for (var i = 0; i < length; i++) {
                        dom.css('-webkit-transform', 'matrix(' + style.toString() + ')');
                    }

                    if (callback) {
                        callback();
                    }

                    clear();
                }
            }

            loop();
        },

        // 将大于10万的数字转化成以万为单位
        convertWan: function (num) {
            var value = num;
            var number = parseInt(value);

            if (number >= 100000000) {
                return Math.floor(parseInt(number) / 100000000) + lang('nian_yi');
            } else if (number >= 100000) {
                return Math.floor(parseInt(number) / 10000) + lang('10k');
            } else {
                return value;
            }
        },

        // 获取锻造装备的品级描述和相应的颜色
        getNewEquipInfo: function (qualityId, type) {

            var str = '';
                        
            switch (parseInt(qualityId)) {
                case 1:
                    
                    if (type == 'title') {
                        str = lang('normal');
                    } else if (type == 'des') {
                        str = lang('newEquip_des1');
                    }
                    break;
                case 2:
                    
                    if (type == 'title') {
                        str = lang('newEquip_title2');
                    } else if (type == 'des') {
                        str = lang('newEquip_des2');
                    }
                    break;
                case 3:
                    
                    if (type == 'title') {
                        str = lang('newEquip_title3');
                    } else if (type == 'des') {
                        str = lang('newEquip_des3');
                    }
                    break;
                case 4:
                    
                    if (type == 'title') {
                        str = lang('newEquip_title4');
                    } else if (type == 'des') {
                        str = lang('newEquip_des4');
                    }
                    break;
                default :

                    if (type == 'title') {
                        str = lang('no');
                    } else if (type == 'des') {
                        str = lang('newEquip_des0');
                    }
                    break;
            }

            return str;
        },

        // 获取套装属性描述
        getSuitAttr: function (type, value) {
            
            var str = '';

            switch (parseInt(type)) {
                case 1:
                    str = lang('wuli') + '+' + value;
                    break;
                case 2:
                    str = lang('zhili') + '+' + value;
                    break;
                case 3:
                    str = lang('tili') + '+' + value;
                    break;
                case 4:
                    str = lang('life') + '+' + value;
                    break;
                case 5:
                    str = lang('p_attack') + '+' + value;
                    break;
                case 6:
                    str = lang('p_defense') + '+' + value;
                    break;
                case 7:
                    str = lang('m_attack') + '+' + value;
                    break;
                case 8:
                    str = lang('m_defense') + '+' + value;
                    break;
                case 9:
                    str = lang('crit_rate') + '+' + value;
                    break;
                case 10:
                    str = lang('crit_damage') + '+' + value;
                    break;
                case 11:
                    str = lang('jingzhun') + '+' + value;
                    break;
                case 12:
                    str = lang('mingzhong') + '+' + value;
                    break;
                case 13:
                    str = lang('miss') + '+' + value;
                    break;
                case 14:
                    str = lang('shipo') + '+' + value;
                    break;
                case 15:
                    str = lang('shiqi') + '+' + value;
                    break;
                case 16:
                    str = lang('kangxuanyun') + '+' + value;
                    break;
                case 17:
                    str = lang('kangbao') + '+' + value;
                    break;
                case 18:
                    str = lang('wuli') + '、' + lang('zhili') + '、' + lang('tili') + '+' + value;
                    break;
                case 19:
                    str = lang('suit_attr19', [value]);
                    break;
                case 20:
                    str = lang('suit_attr20') + '+' + value;
                    break;
                case 21:
                    str = lang('suit_attr21') + '+' + value;
                    break;
                case 22:
                    str = lang('suit_attr22') + '+' + value;
                    break;
                case 23:
                    str = lang('suit_attr23', [value]);
                    break;
                case 24:
                    str = lang('suit_attr24', [value]);
                    break;
                case 25:
                    str = lang('suit_attr25', [value]);
                    break;
                case 26:
                    str = lang('suit_attr26', [value]);
                    break;
                case 27:
                    str = lang('suit_attr27', [value]);
                    break;
                default :
                    break;
            }

            return str;
        },
    };
})();
var helper = new helperClass;

// 视口变化（index.html fit() 在窗口缩放/横竖屏时派发 resize 并刷新 --hj-vw）
// 重应用战斗舞台等比缩放，保证战斗页/结算界面停留期间缩放比始终跟随，
// 不会因窗口尺寸变化而错位（纯展示性重算，无副作用）。
window.addEventListener('resize', function () {
    try { helper.battleStageScale(); } catch (e) {}
});