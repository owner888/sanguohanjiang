/* 老兵召回 */
(function() {
    recallPopClass = function() {
        this.e;
        this.isshow = false;
        this.rewardData = null; //保存领取奖励信息
        this.receiveBtn = null; // 领奖按钮
        this.rewardList = null; // 奖励列表
        this.deleteThread = null;
    };

    recallPopClass.prototype = {
        init: function () {
            
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.initPage(res);
                } else {
                    xGame.toast.show(res.msg);
                    $('#worldloading').hide();
                }
            };

            loadContents({
                'c': 'act_veteran',
                'm': 'index'
            }, render);
        },

        initPage: function (res) {
            
            var that = this;
            that.e = xGame.loadPopTmpl('recall');
            that.receiveBtn = that.e.find('#recall_receive');
            that.rewardList = that.e.find('#recall_list');

            that.e.find('.recall_time').html(res.act_info.act_time);
            that.e.find('.recall_conditions').html(res.act_info.act_level);
            that.buildList(res.reward);
            that.judgeBtnStatus(res.is_reward);
            that.bindAll();

            that.e.show();
            that.isshow = true;
            $('#worldloading').hide();
        },

        //生成list
        buildList: function(data) {

            this.rewardData = data;
            var html = '';
            var item = null;
            var type = 0;

            for (var i = 0, len = data.length; i < len; i++) {

                item = data[i];
                type = parseInt(item.type);

                switch (type) {
                    case 7: // 装备
                        html += '<li class="item reward type' + type + '">';
                        html += '<span class="pic" style="background-image: url(' + xGame.itemurl(item.info.id) + ')";></span>';
                        html += '<em class="name" style="color:' + helper.getItemColor(item.info.quality) + ';">' + item.info.name + '</em>';
                        html += '</li>';
                        break;
                    case 10: // 坐骑
                        html += '<li class="horse reward type' + type + '">';
                        html += '<span class="pic" style="background-position:' + helper.getHorsePostion('small', data[i].info.id) + ';"></span>';
                        html += '<em class="name" style="color:' + helper.getItemColor(data[i].info.quality) + ';">' + item.info.name + '</em>';
                        html += '</li>';
                        break;
                    case 16: // 将领
                        html += '<li class="hero reward type' + type + '">';
                        html += '<span class="pic" style="background-image: url(' + xGame.avatar(item.info.image) + ')";></span>';
                        html += '<em class="name">' + item.info.name + '</em>';
                        html += '</li>';
                        break;
                    case 17: // 免费继承
                        html += '<li class="reward type' + type + '">';
                        html += '<span class="pic"></span>';
                        html += '<em class="name">' + item.name + '</em>';
                        html += '<em class="icon"></em>';
                        html += '</li>';
                        break;
                    default:
                        html += '<li class="reward type' + item.type + ' recall_reward' + (i + 1) + ' rtype' + item.type + '">';
                        html += '<span class="pic"></span>';
                        html += '<em class="num">x' + item.value + '</em>';
                        html += '<em class="name">' + item.name + '</em>';
                        html += '</li>';
                        break;
                }

                if (i != len - 1) {
                    html += '<span class="plus"></span>';
                }

            }

            this.rewardList.html(html);
            item = null;
        },

        // 判断领取按钮状态
        judgeBtnStatus: function (status) {
            
            switch (status) {
                case 0:
                    this.receiveBtn.removeClass('pop_btn_middle').addClass('pop_btn_middle_disable').html(lang('not_reached'));
                    break;
                case 1:
                    this.receiveBtn.addClass('pop_btn_middle').removeClass('pop_btn_middle_disable').html(lang('receive'));
                    break;
                case 2:
                    this.receiveBtn.removeClass('pop_btn_middle').addClass('pop_btn_middle_disable').html(lang('warena_have_fetch'));
                    break;
                default :
                    this.receiveBtn.removeClass('pop_btn_middle').addClass('pop_btn_middle_disable').html(lang('not_reached'));
                    break;
            }
        },

        bindAll: function() {
            var that = this;

            that.e.find('#recall_hidebtn').unbind('touchend').bind('touchend', function(e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.hide();
                e.preventDefault();
            });

            // 领奖按钮
            that.e.delegate('#recall_receive.pop_btn_middle', 'touchend', function (e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                that.getReward();
                e.preventDefault();
            });

            // 查看奖励信息
            that.rewardList.delegate('.reward', 'touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                var $this = $(this);
                var index = that.rewardList.children('.reward').index($this);

                if ($this.hasClass('hero')) {
                    miscpop.showHeroInfo(that.rewardData[index].info);
                } else if ($this.hasClass('item')) {
                    miscpop.showItemInfo(that.rewardData[index].info);
                } else if ($this.hasClass('horse')) {
                    miscpop.showMarchInfo(that.rewardData[index].info);
                }

                $this = null;
                e.preventDefault();
            });
        },

        // 领取奖励
        getReward: function() {

            var that = this;

            var render = function(res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.rewardDown(that.rewardData);
                        that.receiveBtn.addClass('pop_btn_middle_disable').removeClass('pop_btn_middle').html(lang('warena_have_fetch'));
                        player.updateinfo({
                            "silver": res.all_silver,
                            "gold": res.all_gold
                        });
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'act_veteran',
                'm': 'get_reward'
            },
            render);
        },

        //显示领取到的奖励
        rewardDown: function(data) {

            var that = this;
            var item = null;
            var type = 0;
            var html = '<div class="reward_box list">';

            for (var i = 0, len = data.length; i < len; i++) {
                item = data[i];
                type = parseInt(item.type);

                switch (type) {
                    case 7: // 装备
                        html += '<span class="reward reward_down pos' + (i + 1) + ' type'+ type +'" style="color:' + helper.getItemColor(item.info.quality) + ';"><em class="pic" style="background-image:url(' + xGame.itemurl(item.info.id) + ')"></em>' + item.info.name + '</span>';
                        break;
                    case 10: // 坐骑
                        html += '<span class="reward reward_down pos' + (i + 1) + ' type' + type + '" style="color:' + helper.getItemColor(item.info.quality) + ';"><em class="pic" style="background-position:' + helper.getHorsePostion('buff', item.info.id) + '"></em>' + item.info.name + '</span>';
                        break;
                    case 16: // 将领
                        html += '<span class="reward reward_down pos' + (i + 1) + ' type'+ type +'"><em class="pic" style="background-image:url(' + xGame.avatar(item.info.image) + ')"></em>' + item.info.name + '</span>';
                        break;
                    case 17: // 免费继承
                        html += '<span class="reward reward_down pos' + (i + 1) + ' type'+ type +'">' + item.name + '</span>';
                        break;
                    default :
                        html += '<span class="reward reward_down pos' + (i + 1) + ' type' + type + '"><em class="pic"></em>x' + item.value + '</span>';
                        break;
                }
            }

            html += '</div>';
            this.e.find('.content').append(html);

            this.deleteThread = setTimeout(function() {
                that.e.find('.reward_box').remove();
            }, 3000);
        },

        unbindAll: function() {
            this.e.find('#recall_hidebtn').unbind('touchend');
            this.e.undelegate('#recall_receive.pop_btn_middle', 'touchend');
            this.rewardList.undelegate('.reward', 'touchend');
        },

        hide: function() {
            xGame.unlock();
            this.unbindAll();
            this.isshow = false;
            this.rewardData = null;
            this.receiveBtn = null;
            this.rewardList = null;

            if (this.deleteThread != null) {
                clearTimeout(this.deleteThread);
                this.deleteThread = null;
            }
            
            this.e.remove();
            this.e = null;
        },
    };
})();