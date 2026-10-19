/* 精炼继承 */
(function() {
    refineinheritPopClass = function() {
        this.e;
        this.isshow = false;
        this.boxDom;        //装备
        this.buttonDom;     //按钮
        this.itemData;      //装备信息
        this.page = 1;      //当前页码

        this.pid1 = null;   //9号位置装备id
        this.pid2 = null;   //10号位置装备id
        this.touchGid;      //当前点击的装备id
        this.touchPid;      //当前点击装备的在list中的序号
        this.touchPos;      //位置
        this.canMove;       //可以移动的集合
        this.objective;     //目标位置id
        this.nearlyX;       //最近元素的x坐标
        this.nearlyY;       //最近元素的y坐标
        this.nearlyPos;     //最近的元素的位置
        this.zIndex;        //层级
        this.distance;      //检测范围
        this.selected = 0;  //当前选中
        this.preview = false;//预览
    };

    refineinheritPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {

                if (res.status != 1) {
                    if (res.status == 0) {
                        xGame.toast.show(lang('undefined_error'));
                    } else if (res.status == -1) {
                        xGame.toast.show(lang('equiprefinepop_level'));
                    } else if (res.status == -2) {
                        xGame.toast.show(lang('equiprefinepop_refine_msg14'));
                    } else {
                        xGame.toast.show(lang('undefined_error'));
                    }
                    $('#worldloading').hide();
                    return;
                }

                that.e = xGame.loadPopTmpl('refineinherit');
                that.e.find('.content').find('#hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    if (xGame.checkLock(true)) {
                        return false;
                    }
                    that.hide();
                    xGame.showpop('equiprefine');
                    e.preventDefault();
                });

                that.boxDom = that.e.find('#refineinheritpop_content');
                that.buttonDom = that.e.find('#refineinheritpop_button');

                that.itemData = res.list;

                that.zIndex = 10;
                that.distance = that.getDistance(100, 100);

                /* 设置拖动区域 */
                that.canMove = {
                    0 : {
                        'left': 58,
                        'top': 70
                    },
                    1 : {
                        'left': 205,
                        'top': 70
                    },
                    2 : {
                        'left': 350,
                        'top': 70
                    },
                    3 : {
                        'left': 58,
                        'top': 209
                    },
                    4 : {
                        'left': 205,
                        'top': 209
                    },
                    5 : {
                        'left': 350,
                        'top': 209
                    },
                    6 : {
                        'left': 58,
                        'top': 344
                    },
                    7 : {
                        'left': 205,
                        'top': 344
                    },
                    8 : {
                        'left': 350,
                        'top': 344
                    },
                    9 : {
                        'left': 549,
                        'top': 150
                    },
                    10 : {
                        'left': 763,
                        'top': 150
                    }
                };

                that.objective = {
                    'pos9' : 'block',
                    'pos10' : 'block'
                };

                that.buildItemList();
                that.bindAll();
                that.bindItem();

                that.e.show();
                $('#worldloading').hide();

                if (equiprefinepop) {
                    equiprefinepop.hide();
                }

                if (systempop) {
                    systempop.nowPop = 'refineinherit';
                }

            };
            loadContents({
                'c': 'refine',
                'm': 'item_index'
            },
            initRender, true); //加载数据
            initRender = null;
        },

        getInnerHtml: function(data) {
            var that = this;
            that.boxDom.find('#item9').find('.refineinheritpop_hammer').hide();
            that.boxDom.find('#item10').find('.refineinheritpop_hammer').attr('class','refineinheritpop_hammer hammer' + data.right.hammer_quality).html(data.right.level == 0 ? '' : data.right.level).show();
            that.boxDom.addClass('move');            
            that.e.find('#refineinheritpop_anim').attr('class', 'move');
        },

        buildItemList: function(page) {
            var that = this;
            var i = (parseInt(page) - 1) * 9 || 0;
            var html = "";
            var box = "";
            var data = that.itemData;            

            if(that.pid1 || that.pid1 === 0) {
                //console.log('9位置上有装备,装备pid是' + that.pid1);
                html += that.fillPos9(9);
                box += '<div id="box_9" class="box' + that.itemData[that.pid1].quality + '"></div>';
            } else {
                box += '<div id="box_9"></div>';
            }
            if(that.pid2 || that.pid2 === 0) {
                //console.log('10位置上有装备,装备pid是' + that.pid2);
                html += that.fillPos9(10);
                that.boxDom.find('#box_10').addClass('box' + that.itemData[that.pid2].quality);
                box += '<div id="box_10" class="box' + that.itemData[that.pid2].quality + '"></div>';
            } else {
                box += '<div id="box_10"></div>';
            }

            for (var n = 0; n < 9; n++) {
                var item = data[i];
                if(item && i != that.pid1 && i != that.pid2) {
                    html += '<div id="item' + n + '" class="itembox" pos="' + n + '" itemid="' + item.id + '"  pid="' + i + '">' + (item.level != 0 ? '<span class="level">' + item.level + '</span>': '') + '<div class="pic" style="background:url(' + xGame.itemurl(item.equipid) + ') center center no-repeat;">';
                    var display = item.hammer <= 0 ? 'none' : 'block';
                    html += '<span class="refineinheritpop_hammer hammer' + item.hammer_quality + '" style ="display: '+ display +'">' + item.hammer + '</span>';
                    html += '</div>';
                    html += '<span class="item_name" style="color: ' + helper.getItemColor(item.quality) + ';display:none;">' + item.name + '</span>';
                    html += '</div>';
                }

                var quality = item ? item.quality : 1;
                box += '<div class="itembg c_i_bj' + quality + (n == that.selected ? ' selected': '') + '" id="itembg' + n + '"></div>';
                i++;
            }
            that.boxDom.html(box + html);
        },

        fillPos9: function(pos) { //翻页后处理位置9 10上的装备
            var that = this;
            var pidpos = pos == 9 ? that.pid1 : that.pid2;
            var html = '<div id="item' + pos + '" class="itembox" pos="' + pos + '" itemid="' + that.objective['pos' + pos] + '" pid="' + pidpos + '" style="z-index: auto; left: ' + that.canMove[pos].left + 'px; top: ' + that.canMove[pos].top + 'px;">' + (that.itemData[pidpos].level != 0 ? '<span class="level">' + that.itemData[pidpos].level + '</span>': '') + '<div class="pic" style="background:url(' + xGame.itemurl(that.itemData[pidpos].equipid) + ') center center no-repeat;">';
            var display = that.itemData[pidpos].hammer <= 0 ? 'none' : 'block';
            html += '<span class="item_name" style="color: ' + helper.getItemColor(that.itemData[pidpos].quality) + '">' + that.itemData[pidpos].name + '</span>';
            html += '</div>';
            html += '<span class="refineinheritpop_hammer hammer' + that.itemData[pidpos].hammer_quality + '" style ="display: '+ display +'">' + that.itemData[pidpos].hammer + '</span>';
            html += '</div>';
            return html;
        },

        bindAll: function() {
            var that = this;

            //翻页按钮
            that.e.undelegate('button.paging', 'touchend').delegate('button.paging', 'touchend', function() {
                var type = $(this).attr('id') == 'prevpage' ? -1 : 1;
                that.page += type;
                if(that.page <= 0) {
                    that.page = 1;
                }
                if(that.page >= Math.ceil(that.itemData.length / 9)) {
                    that.page = Math.ceil(that.itemData.length / 9);
                }

                that.buttonDom.find('#page_no').html(that.page);

                that.buildItemList(that.page);
                that.getBack(true);
            });

            //玩法说明
            that.buttonDom.undelegate('#refineinheritpop_info_btn', 'touchend').delegate('#refineinheritpop_info_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#refineinheritpop_small').css('display', 'block');
            });
            that.e.find('#refineinheritpop_small_hide').unbind('touchend').bind('touchend', function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#refineinheritpop_small').css('display', 'none');
            });

            //继承按钮
            that.buttonDom.undelegate('#refineinherit_btn.pop_btn_big', 'touchend').delegate('#refineinherit_btn.pop_btn_big', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.requstRefine(that.objective['pos9'], that.objective['pos10'], 'inherit');
            });

            //预览按钮
            that.buttonDom.undelegate('#inherit_preview.pop_btn_small', 'touchend').delegate('#inherit_preview.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.judgeEmpty(true);
                that.preview = true;
            });
        },

        /*拖动主要逻辑*/
        bindItem: function() {
            var that = this;

            var ex = 0;
            var ey = 0;
            var ox = 0;
            var oy = 0;
            var target = null;

            that.boxDom.undelegate('div.itembox', 'touchstart').delegate('div.itembox', 'touchstart',
            function(event) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.touchGid = parseInt($(this).attr('itemid'));
                that.touchPid = parseInt($(this).attr('pid'));
                that.touchPos = parseInt($(this).attr('pos'));
                that.touchItemData = that.itemData[that.touchPos];
                that.selected = that.touchPos;
                that.boxDom.find('#box_' + that.touchPos).removeClass();
                that.preview = false;
                that.getBack(that.touchPos);

                that.boxDom.find('div').removeClass('selected');
                that.boxDom.find('#itembg' + that.touchPos).addClass('selected');
                if($(this).find('.refineinheritpop_hammer')) {
                    $(this).find('.refineinheritpop_hammer').hide();
                }
                $(this).find('.item_name').hide();

                ex = parseInt(that.canMove[that.touchPos].left);
                ey = parseInt(that.canMove[that.touchPos].top);

                ox = parseInt(event.touches[0].pageX);
                oy = parseInt(event.touches[0].pageY);

                $(this).addClass('ismoving');
                $(this).css('z-index', that.zIndex++);
                target = $(this);
            });

            that.e.unbind('touchmove').bind('touchmove',function(event) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (target) {
                    var nX = parseInt(event.touches[0].pageX);
                    var nY = parseInt(event.touches[0].pageY);

                    var newX = nX - ox;
                    var newY = nY - oy;
                    var realX = ex + newX;
                    var realY = ey + newY;
                    target.css({
                        left: realX + 'px',
                        top: realY + 'px'
                    });
                }
            });

            that.boxDom.undelegate('div.ismoving', 'touchend').delegate('div.ismoving', 'touchend',
            function(event) {
                target = null;
                $(this).removeClass('ismoving');
                if(that.itemData[that.touchPid].hammer != 0) {
                    $(this).find('.refineinheritpop_hammer').show();
                }
                $(this).css('z-index', 'auto');
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var obj = $(this);
                var myx = parseInt(obj.css('left').replace('px', ''));
                var myy = parseInt(obj.css('top').replace('px', ''));

                /*移动的是已在容器里的装备*/
                if (that.touchPos > 8) {
                    /*有位置可以移动，判断最近的位置*/
                    if (that.checkPos(myx, myy)) { //合法，到最近的位置上去
                        if (that.nearlyPos == that.touchPos) { //自己的范围内，返回自己范围
                            obj.animate({
                                'left': that.nearlyX + 'px',
                                'top': that.nearlyY + 'px'
                            },
                            100, 'ease', function() {
                            });

                            obj.find('.item_name').show();
                            that.boxDom.find('#box_' + that.touchPos).addClass('box' + that.itemData[that.touchPid].quality);
                        } else { //对面
                            //如果对面是空的，过去，
                            var duiId = that.touchPos == 9 ? 10 : 9;

                            //保存数据
                            var boxClass = that.boxDom.find('#box_' + duiId).attr('class');
                            var gid = that.objective['pos' + duiId];

                            obj.animate({
                                'left': that.nearlyX + 'px',
                                'top': that.nearlyY + 'px'
                            },
                            100, 'ease',
                            function() {
                                obj.attr({
                                    'id': 'item' + duiId,
                                    'pos': duiId
                                });

                                obj.find('.item_name').show();
                                that.boxDom.find('#box_' + duiId).removeClass().addClass('box' + that.itemData[that.touchPid].quality);
                                //debugger;
                            });
                            if (that.objective['pos' + duiId] == 'block') {
                                that.objective['pos' + that.touchPos] = 'block';
                                that.touchPos == 9 ? that.pid1 = null : that.pid2 = null;
                            } else { //对面不是空的，交换

                                that.boxDom.find('#item' + duiId).animate({
                                    'left': that.canMove[that.touchPos].left + 'px',
                                    'top': that.canMove[that.touchPos].top + 'px'
                                },
                                100, 'ease',
                                function() {
                                    $(this).attr({
                                        'id': 'item' + that.touchPos,
                                        'pos': that.touchPos,
                                    });


                                    $(this).find('.item_name').show();
                                    that.boxDom.find('#box_' + that.touchPos).addClass(boxClass);
                                });
                                that.objective['pos' + that.touchPos] = gid;
                            }
                            //交换pid
                            var temp = that.pid1;
                            that.pid1 = that.pid2;
                            that.pid2 = temp;
                            //设置参数
                            that.objective['pos' + duiId] = that.touchGid;
                        }
                    } else { //不合法，回到左边装备中或者消失
                        //不是本页的装备直接消失
                        var page = Math.floor((that.touchPos == 9 ? that.pid1 / 9 : that.pid2 / 9) + 1);
                        if(page != that.page){
                            obj.remove();
                            that.objective['pos' + that.touchPos] = 'block';
                        } else {
                            obj.animate({
                                'left': that.canMove[that.touchPid % 9].left + 'px',
                                'top': that.canMove[that.touchPid % 9].top + 'px'
                            },
                            100, 'ease',
                            function() {
                                obj.attr({
                                    'pos': that.touchPid % 9,
                                    'id': 'item' + that.touchPid % 9
                                });
                                obj.find('.refineinheritpop_hammer').attr('class', 'refineinheritpop_hammer hammer' + that.itemData[that.touchPid].hammer_quality).html(that.itemData[that.touchPid].hammer == '' ? '' : that.itemData[that.touchPid].hammer);
                            });
                        }

                        var duiId = that.touchPos == 9 ? 10 : 9;
                        var pid = that.boxDom.find('#item' + duiId).attr('pid');
                        if(pid != undefined) {
                            var hammer = (that.itemData[pid].hammer == 0 ? '' : that.itemData[pid].hammer);
                            var hammer_quality = that.itemData[pid].hammer_quality;

                            that.boxDom.find('#item' + duiId).find('.refineinheritpop_hammer').attr({
                                'class':'refineinheritpop_hammer hammer' + hammer_quality,
                                'style': 'display:block'
                            }).html(hammer);
                        }
                        that.objective['pos' + that.touchPos] = 'block';
                        that.touchPos == 9 ? that.pid1 = null : that.pid2 = null;
                    }
                } else { //移动的没有在容器里的装备
                    /*有位置可以移动，判断最近的位置*/

                    if (that.checkPos(myx, myy)) { //合法，到最近的位置上去

                        if (that.nearlyPos == that.touchPos) {
                            obj.animate({
                                'left': that.nearlyX + 'px',
                                'top': that.nearlyY + 'px'
                            },
                            100, 'ease',
                            function() {
                                obj.css({
                                    'left': that.canMove[that.touchPos].left + 'px',
                                    'top': that.canMove[that.touchPos].top + 'px'
                                });
                                that.boxDom.find('#box_' + that.nearlyPos).removeClass().addClass('box' + that.itemData[that.touchPid].quality);
                            });

                            $(this).find('.item_name').show();

                        } else { //选择的装备不在容器内
                            //如果不是空位，移回原来目标元素或者消失，后面都一样：当前元素改成目标元素，新建一个初始元素，
                            if (that.objective['pos' + that.nearlyPos] != 'block') {
                                var page = Math.floor((that.nearlyPos == 9 ? that.pid1 / 9 : that.pid2 / 9) + 1);

                                if(page != that.page){
                                    that.boxDom.find('#item' + that.nearlyPos).remove();
                                    that.objective['pos' + that.touchPos] = 'block';
                                } else {
                                    var pid = parseInt(that.boxDom.find('#item' + that.nearlyPos).attr('pid'));
                                    var pos = pid % 9;

                                    that.boxDom.find('#item' + that.nearlyPos).find('.item_name').hide();
                                    that.boxDom.find('#item' + that.nearlyPos).animate({
                                        'left': that.canMove[pos].left + 'px',
                                        'top': that.canMove[pos].top + 'px'
                                    },
                                    100, 'ease',
                                    function() {
                                        $(this).attr({
                                            'id': 'item' + pos,
                                            'pos': pos
                                        });
                                        if($(this).find('.refineinheritpop_hammer').html() != 0) {
                                        $(this).find('.refineinheritpop_hammer').show();
                                        }
                                    });
                                }
                            }
                            obj.animate({
                                'left': that.nearlyX + 'px',
                                'top': that.nearlyY + 'px'
                            },
                            100, 'ease',
                            function() {
                                obj.attr({
                                    'id': 'item' + that.nearlyPos,
                                    'pos': that.nearlyPos
                                });

                                obj.find('.item_name').show();
                                that.boxDom.find('#box_' + that.nearlyPos).removeClass().addClass('box' + that.itemData[that.touchPid].quality);
                            });

                            //设置数据
                            that.objective['pos' + that.nearlyPos] = that.touchGid;
                        }
                    } else { //不合法
                        that.nearlyPos = null;
                        //位置没到位,回到原处
                        obj.animate({
                            'left': that.canMove[that.touchPos].left + 'px',
                            'top': that.canMove[that.touchPos].top + 'px'
                        },
                        100, 'ease');
                    }
                }

                if(that.nearlyPos == 9) {
                    that.pid1 = that.objective['pos9'] == 'block' ? null : that.touchPid;
                } else if(that.nearlyPos == 10) {
                    that.pid2 = that.objective['pos10'] == 'block' ? null : that.touchPid;
                }

                if(that.pid1 == null) {
                    that.e.find('#refineinheritpop_item_l').show();
                } else {
                    that.e.find('#refineinheritpop_item_l').hide();
                }
                if (that.pid2 == null) {
                    that.e.find('#refineinheritpop_item_r').show();
                }else {
                    that.e.find('#refineinheritpop_item_r').hide();
                }

                that.judgeEmpty();
                that.checkForPreviewBtn();
            });
        },

        //预览继承
        judgeEmpty: function(type) {
            var that = this;
            //目标位置都不是空位
            if (that.pid1 != null && that.pid2 != null) {
                that.buttonDom.find('#inherit_preview').attr('class', 'btn pop_btn_small');
            } else {
                that.buttonDom.find('#inherit_preview').attr('class', 'btn pop_btn_small_disable')
                that.e.find('#refineinheritpop_anim').attr('class', '');
            }

            if(type) {
                that.requstRefine(that.objective['pos9'], that.objective['pos10']);
            }
        },
    
        /*这个功能的作用是传进参数，检测边界，
         * 返回true时代表有位置可以到达，再设置that.nearlyX和that.nearlyY完成操作；
         * 返回false时就代表直接返回原来的位置
         */
        checkPos: function(x, y) {
            var that = this;

            /*结果集*/
            var result = [];
            that.nearlyX = that.nearlyY = that.nearlyPos = null;

            /*第一步，告诉程序有几个点可以移动，不用排除自己*/
            ////console.log(that.canMove);
            for (var i = 9; i < 11; i++) {
                if (that.objective['pos' + i] != undefined) {
                    result.push({
                        'id': i,
                        'distance': that.getDistance(Math.abs(that.canMove[i].left - x), Math.abs(that.canMove[i].top - y))
                    });
                }
            }

            /*按属性排大小，方法写在base.js里面*/
            result.sort(helper.compare("distance"));

            if (result.length != 0) {
                that.nearlyPos = result[0]['id'];
                /*不管如何都返回最近的x，y*/
                that.nearlyX = that.canMove[result[0]['id']].left;
                that.nearlyY = that.canMove[result[0]['id']].top;

                var myDistance = that.getDistance(Math.abs(that.nearlyX - x), Math.abs(that.nearlyY - y));

                //console.log('距离 = ' + myDistance);
                //console.log('最近的posid = ' + that.nearlyPos);
                if (myDistance < that.distance * 3 / 4) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }

        },

        getDistance: function(x, y) {
            return Math.sqrt(x * x + y * y);
        },

        /*判断按钮是否该变灰*/
        checkForPreviewBtn: function() {
            if (this.pid1 != null && this.pid2 != null) {
                this.buttonDom.find('#refineinherit_btn').attr('class', 'btn pop_btn_big');
            } else {
                this.buttonDom.find('#refineinherit_btn').attr('class', 'btn pop_btn_big_disable');
                this.boxDom.removeClass('move');
            }
        },

        /*恢复预览*/
        getBack: function(pos) {

            this.e.find('#refineinheritpop_anim').removeClass('move');
            this.boxDom.removeClass('move');
            if(this.pid1 != null) {
                var data = this.itemData[this.pid1];
                var hammerDom = this.boxDom.find('#item9').find('.refineinheritpop_hammer');

                hammerDom.attr('class', 'refineinheritpop_hammer hammer' + data.hammer_quality).html(data.hammer == 0 ? '' : data.hammer);
                if(pos != 9) {
                    hammerDom.show();
                }
            }
            if(this.pid2 != null) {
                var data = this.itemData[this.pid2];
                var hammerDom = this.boxDom.find('#item10').find('.refineinheritpop_hammer');

                hammerDom.attr('class', 'refineinheritpop_hammer hammer' + data.hammer_quality).html(data.hammer == 0 ? '' : data.hammer).show();
                if(pos != 10) {
                    hammerDom.show();
                }
            }
        },

        /*继承请求*/
        requstRefine: function(pid1, pid2, type) {
            var that = this;

            var initRender = function(res) {
                if(res.status == 0) {
                    xGame.toast.show(lang('undefined_error'));
                    return;
                } else if(res.status == 2) {
                    xGame.toast.show(lang('refineinheritpop_err_msg'));
                    return;
                } else if(res.status == -1) {
                    xGame.toast.show(lang('equiprefinepop_level'));
                    return;
                } else if(res.status == -2) {
                    xGame.toast.show(lang('refineinheritpop_item_error'));
                    return;
                } else if(res.status == -3) {
                    xGame.toast.show(lang('refineinheritpop_notfound'));
                    return;
                } else if(res.status == -4) {
                    xGame.toast.show(lang('refineinheritpop_not_inherit'));
                    return;
                } else if(res.status == -5) {
                    xGame.toast.show(lang('refineinheritpop_not_level'));
                    return;
                } else if (res.status == 1){
                    if(type == 'preview') {
                        that.getInnerHtml(res);
                    } else {
                        var id1,id2;
                        id1 = parseInt(that.boxDom.find('#item9').attr('pid'));
                        id2 = parseInt(that.boxDom.find('#item10').attr('pid'));

                        that.itemData[id1].hammer = res.left.hammer;
                        that.itemData[id1].hammer_quality = res.left.hammer_quality;
                        that.itemData[id2].hammer = res.right.hammer;
                        that.itemData[id2].hammer_quality = res.right.hammer_quality;
                        that.inheritAnim();
                    }
                } else {                    
                    xGame.toast.show(lang('undefined_error'));
                    return;
                }
            };

            var type = type || 'preview'; //判断是继承还是预览
            loadContents({
                'c': 'refine',
                'm': type,
                'id1': pid1,
                'id2': pid2,
            },
            initRender, true); //加载数据
            initRender = null;
        },

        //继承动画
        inheritAnim: function() {
            xGame.islock = true;
            var that = this;
            var obj = that.boxDom.find('#item9').clone().attr('id', 'temp');
            obj.appendTo(that.boxDom).addClass('inherit_anim').one('webkitAnimationEnd',
            function() {
                obj.remove();
                obj = null;
                that.boxDom.find('#item10').find('.pic').addClass('tada');
                that.shineAnim(function() {
                    that.boxDom.removeClass('move');
                    that.boxDom.find('#item10').find('.refineinheritpop_hammer').attr('style', 'display: block; opacity: 1;');
                    that.boxDom.find('#item10').find('.pic').removeClass('tada');
                    if (that.preview == false) {
                    that.getBack(true);
                    }

                    xGame.unlock();
                });
            });
        },

        shineAnim: function(removeclass) {
            var movieclip;
            //创建动画底图
            var mc = new movieClipClass({
                "img": staticUrl + 'img/pop/inheritpop_nim.png',
                "width": 256,
                "height": 150,
                "x": 700,
                "y": 115,
                "callback": removeclass,
                "loop": false,
                "endDestory": true,
                "frames": [{
                    rect: [0, 0],
                    interval: 50
                },
                {
                    rect: [256, 0],
                    interval: 50
                },
                {
                    rect: [512, 0],
                    interval: 50
                },
                {
                    rect: [768, 0],
                    interval: 50
                },
                {
                    rect: [0, 150],
                    interval: 50
                },
                {
                    rect: [256, 150],
                    interval: 50
                },
                {
                    rect: [512, 150],
                    interval: 50
                },
                {
                    rect: [768, 150],
                    interval: 50
                },
                {
                    rect: [0, 300],
                    interval: 50
                },
                {
                    rect: [256, 300],
                    interval: 50
                }],
                "zindex": this.zIndex,
            });
            //开始播放
            mc.appendTo(this.boxDom);
            mc.play();
            mc = null;
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('#hidebtn').unbind('touchend');
                this.e.find('#refineinheritpop_small_hide').unbind('touchend');
                this.e.unbind('touchmove');
                this.e.undelegate('button.paging', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.buttonDom) {
                this.buttonDom.undelegate('#refineinheritpop_info_btn', 'touchend');
                this.buttonDom.undelegate('#refineinherit_btn.pop_btn_big', 'touchend');
                this.buttonDom.undelegate('#inherit_preview.pop_btn_small', 'touchend');
                this.buttonDom.remove();
                this.buttonDom = null;
            }
            if (this.boxDom) {
                this.boxDom.undelegate('div.itembox', 'touchstart');
                this.boxDom.undelegate('div.ismoving', 'touchend');
                this.boxDom.remove();
                this.boxDom = null;
            }
        },

        hide: function() {

            xGame.unlock();

            this.isshow = false;
            
            this.pid1 = null;
            this.pid2 = null;
            this.itemData = null;
            this.zIndex = null;
            this.canMove = null;
            this.objective = null;
            this.touchPid = null;
            this.touchGid = null;
            this.page = 1;
            this.nearlyX = null;
            this.nearlyY = null;
            this.nearlyPos = null;
            this.distance = null;
            this.selected = 0;
            this.preview = false;
            this.unbindAll();
          
        }
    };
})();