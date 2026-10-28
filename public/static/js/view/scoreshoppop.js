/* 背包 */
(function() {
    scoreshopPopClass = function() {
        this.e = null;
        this.isshow = false;
        this.data = null; //以武器id为键
        this.nowViewId = 0; //当前的ID
        this.myScroll = null;
        this.deleteThread = null;
        this.domRight = null;       // 宝箱信息DOM
        this.domRightBox = null;    // 宝箱
        this.name = null;           // 宝箱名字
        this.reward = null;         // 宝箱描述
        this.score = null;          // 当前积分
        this.btn = null;
    };
    scoreshopPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                that = null;
                return;
            }

            var initRender = function(res) {

                switch (res.status) {
                    case 1:
                        that.initPage(res);
                        break;
                    case -1:
                        xGame.toast.show(lang('scoreshoppop_no'));
                        break;
                    default :
                        xGame.toast.show(lang('internet_error'));
                        break;
                }
            };

            loadContents({
                'c': 'war',
                'm': 'point_shop'
            },
            initRender, true); //加载数据
            initRender = null;
        },

        initPage: function (res) {
            
            var that = this;
            res.list = $.isArray(res.list) ? res.list : helper.array_values(res.list || []);
            var currentPoint = (res.point != null) ? res.point : ((res.points != null) ? res.points : 0);

            // 保存数据
            that.data = {};
            for (var i = 0; i < res.list.length; i++) {
                that.data[res.list[i]['id']] = res.list[i];
            }

            that.e = xGame.loadPopTmpl('scoreshop');

            that.domRight = that.e.find('.iteminfo_wrap');
            that.domRightBox = that.domRight.children('.itembox');
            that.name = that.domRight.children('.name');
            that.reward = that.domRight.children('.iteminfo_p');
            that.score = that.e.find('.score_text');
            that.btn = that.domRight.children('#scoreshoppop_sellbtn');

            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend', function(e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.hide();
                e.preventDefault();
            });

            // 购买按钮
            that.domRight.undelegate('#scoreshoppop_sellbtn.pop_btn_big', 'touchend').delegate('#scoreshoppop_sellbtn.pop_btn_big', 'touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.buyBox(res, that.nowViewId);
                e.preventDefault();
            });

            that.score.html(currentPoint);
            that.buildlist(res.list);
            that.e.show();
            $('#worldloading').hide();
        },

        buildlist: function(data) {
            var that = this;

            if (that.myScroll != null) {
                that.myScroll.scrollTo(0, 0);
                that.myScroll.destroy();
                delete that.myScroll;
            }

            var html = '<li class="cs_item scoreshoppop_list">';
            var n = 0;

            for (var i = 0, len = data.length; i < len; i++) {
                
                if (n > 0 && n % 9 == 0) { //下一页了
                    html += '</li><li class="cs_item scoreshoppop_list">';
                }

                var item = data[i];

                html += '<div id="scoreshopitem' + item.id + '" class="c_i_bj'+ item.type +' scoreshopitem ' + (n == 0 ? ' selected': '') + '" itemid="' + item.id + '"><span class="box"></span></div>';
                n++;
            }

            // 补空格
            var repeat_s = '<div class="scoreshopitem c_i_bj1"></div>';
            
            if (n % 9 == 0) {
                html += '';
            } else {
                html += helper.str_repeat(repeat_s, 9 - n % 9);
            }

            html += '</li>';
            that.e.find('.carousel_content').html(html);

            // 创建旋转门 domid, 单页宽度
            that.myScroll = xGame.carousel('scoreshoplist_carousel', 450);

            // 绑定事件
            $('#scoreshoplist_carousel').undelegate('.scoreshopitem', 'click').delegate('.scoreshopitem', 'click', function() {
                scoreshoppop.viewItem($(this));
            });

            // 绑定右侧数据
            //获取数组第一条数据，通用方法
            var rightdata = helper.array_first(data);
            if (rightdata) {
                that.nowViewId = rightdata.id;
                that.buildRight(rightdata);
            }
        },

        buildRight: function(data) {

            var that = this;
            var gettype = 0;
            var getvalue = 0;
            var html = '';

            that.domRightBox.attr('class', 'itembox c_i_bj' + data.type);
            that.name.html(data.name).css('color', helper.getItemColor(data.type));

            for (var i = 1; i <= 5; i++) {
                gettype = parseInt(data['gettype' + i]);
                getvalue = parseInt(data['getvalue' + i]);

                if (gettype == 7) { // 装备
                    html += '<span class="reward type' + gettype + '" style="color:'+ helper.getItemColor(data.info['equip' + i].quality) +'"><em class="item" style="background-image:url('+ xGame.itemurl(data.info['equip' + i].id) +')"></em>' + data.info['equip' + i].name + '</span>';
                } else if (gettype == 10) { // 坐骑
                    html += '<span class="reward type' + gettype + '" style="color:'+ helper.getItemColor(data.info['mount' + i].quality) +'"><em class="horse" style="background-position:'+ helper.getHorsePostion('buff', data.info['mount' + i].id) +'"></em>' + data.info['mount' + i].name + '</span>';
                } else if (gettype >= 15 && gettype <= 23) { // 精华
                    html += '<span class="reward essence type' + gettype + '" style="color:'+ helper.getItemColor(data.info['essence' + i].quality) +';"><em class="item" style="background-image:url('+ xGame.essence(data.info['essence' + i].id) +')"></em>x' + data.info['essence' + i].num + '</span>';
                } else if (gettype == 0) {
                    continue;
                } else {
                    html += '<span class="reward type' + gettype + '">x'+ (getvalue >= 100000 ? player.getWan(getvalue) : getvalue) +'</span>';
                }
            }

            that.reward.html(html);
            that.domRight.children('.iteminfo_desc').html(data.desc);

            if (parseInt(that.score.html()) < parseInt(data.integral)) {
                that.btn.attr('class', 'btn pop_btn_big_disable');
                that.domRight.find('.itemprice_text').addClass('red').html(data.integral);
            } else {
                that.btn.attr('class', 'btn pop_btn_big');
		that.domRight.find('.itemprice_text').removeClass('red').html(data.integral);
            }

            html = '';

        },

        buyBox: function (res, id) {
            var that = this;

            xGame.confirm.show(lang('scoreshoppop_title'), lang('scoreshoppop_tips', [res.list[id - 1].integral]), 2, function() {
                    xGame.confirm.hide(
                        function() {
                            that.buyConfirm(id);
                        }
                    );
                }, function() {
                    xGame.confirm.hide();
                }
            );
        },

        buyConfirm: function (id) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var buy = function (res) {
                xGame.unlock();
                switch (res.status) {
                    case 1:
                            that.rewardDown(res.list);
                            that.score.html(res.point);
                            player.updateinfo({
                                'silver' : res.all_silver
                            });

                            if (parseInt(res.point) < parseInt(res.list.integral)) {
                                that.btn.attr('class', 'btn pop_btn_big_disable');
                            } else {
                                that.btn.attr('class', 'btn pop_btn_big');
                            }
                        break;
                    case -1:
                        xGame.toast.show(lang('scoreshoppop_no'));
                        break;
                    case 2:
                        xGame.toast.show(lang('scoreshoppop_score'));
                        break;
                    default:
                        xGame.toast.show(lang('internet_error'));
                        break;
                }

            }

            loadContents({
                'c': 'war',
                'm': 'point_buy',
                'id': id
            }, buy);
            buy = null;
        },

        // 掉下的物品
        rewardDown: function(data) {
            
            var that = this;
            data = data || { gettype1: 1, getvalue1: 50000, gettype2: 0, getvalue2: 0, gettype3: 0, getvalue3: 0, gettype4: 0, getvalue4: 0, gettype5: 0, getvalue5: 0 };
            var html = '';
            var gettype = 0;
            var getvalue = 0;
            html += '<div class="scoreshop_reward_box">';

            for (var i = 1;i <= 5; i ++) {
                gettype = parseInt(data['gettype' + i]);
                getvalue = parseInt(data['getvalue' + i]);
                
                if (gettype == 7) {
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '">';
                    html += '<em class="item" style="background-image: url(' + xGame.itemurl(data.info['equip' + i].id) + ');"></em>';
                    html += '<b style="color: ' + helper.getItemColor(data.info['equip' + i].quality) + '">' + data.info['equip' + i].name + '</b>';
                    html += '</span>';
                } else if (gettype == 10) {
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '">';
                    html += '<em class="item" style="background-position:' + helper.getHorsePostion('buff', data.info['mount' + i].mid) + ';"></em>';
                    html += '<b style="color: ' + helper.getItemColor(data.info['mount' + i].quality) + '">' + data.info['mount' + i].name + '</b>';
                    html += '</span>';
                } else if (gettype == 0) {
                    continue;
                } else if (gettype >= 15 && gettype <= 23) {
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + ' essence">';
                    html += '<em class="item" style="background-image: url(' + xGame.essence(data.info['essence' + i].id) + ');"></em>';
                    html += '<b style="color: ' + helper.getItemColor(data.info['essence' + i].quality) + '">x' + data.info['essence' + i].num + '</b>';
                    html += '</span>';
                } else {
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '"><em></em>x' + (getvalue >= 100000 ? player.getWan(getvalue) : getvalue) + '</span>';

                }
            }

            html += '</div>';
            that.e.find('#scoreshoppop_wrap').append(html);
            
            that.deleteThread = setTimeout(function() {
                
                if (that.e) {
                    that.e.find('.scoreshop_reward_box:first').remove();
                }

            }, 3000);
            
            html = '';
        },

        // 查看宝箱
        viewItem: function(obj) {
            var that = this;

            var id = obj.attr('itemid');
            if (that.data[id]) {
                $('#scoreshopitem' + that.nowViewId).removeClass('selected');
                obj.addClass('selected');
                that.nowViewId = id;
                that.buildRight(that.data[id]);
                obj = null;
            }

        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.domRight) {
                this.domRight.undelegate('#scoreshoppop_sellbtn.pop_btn_big', 'touchend');
                this.domRight.remove();
                this.domRight = null;
            }
            $('#scoreshoplist_carousel').undelegate('.scoreshopitem', 'click');
            
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            if(this.myScroll) {
                this.myScroll.destroy();
                this.myScroll = null;
            }
            this.data = null;
            this.nowViewId = null;

            if (this.deleteThread) {
                clearTimeout(this.deleteThread);
                this.deleteThread = null;
            }
            
            this.domRight = null;
            this.domRightBox = null;
            this.name = null;
            this.reward = null;
            this.score = null;

            this.unbindAll();
            
        },
    };
})();