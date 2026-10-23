/*通关奖励*/
(function() {
    newpassrewardPopClass = function() {
        this.e;
        this.rewardScroller;
        this.rewardData;
    };
    newpassrewardPopClass.prototype = {
        init: function() {
            var self = this;

            if (this.isshow) {
                $('#worldloading').hide();
                return;
            }

            var render = function (res) {
                switch (parseInt(res.status)) {
                    case 1:
                        self.rewardData = res.list;
                        self.initPage(res.list);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }

                if (res.status != 1) {
                    $('#worldloading').hide();
                }
            };
            loadContents({
                'c': 'map',
                'm': 'get_newreward_index',
                'levelid': player.info.missionlevel
            }, render);

            render = null;

        },

        initPage: function(res) {
            var self = this;
            this.e = xGame.loadPopTmpl('newpassreward');
            //关闭按钮
            this.e.find('#newpassreward_hide').unbind('touchend').bind('touchend', function(e) {
                newpassrewardpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });
            // 奖励列表中的奖励项
            this.e.find('#newpassreward_scroller').undelegate('.img', 'click').delegate('.img', 'click', function (e) {
               
               if (xGame.checkLock(true)) {
                    return true;
                }
                var parent = $(this).parent();
                if (parent.hasClass('type12')) {
                    var index = $('#newpassreward_scroller .box').index(parent); // 取得武将在所有奖励中的索引
                    miscpop.showHeroInfo(self.rewardData[index].general);
                } else if (parent.hasClass('type8') || parent.hasClass('type30') ) {
                    var index = $('#newpassreward_scroller .box').index(parent); // 取得装备在所有奖励中的索引
                    miscpop.showItemInfo(self.rewardData[index].info);
                }

                e.preventDefault();
                e.stopPropagation();
            });

            var html = this.getHtml(res);
            this.e.find('#newpassreward_list').html(html).width(res.length * 163 + 'px');
            html = null;
            if (this.rewardScroller == null) {
                this.rewardScroller = new iScroll('newpassreward_scroller', {
                    snap: 'li',
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            this.rewardScroller.refresh();
            //绑定滑动门左右按钮
            this.e.find('#scroller_btn_left').unbind('touchend').bind('touchend',
            function() {
                if (self.rewardScroller && self.rewardScroller.pagesX && self.rewardScroller.pagesX.length > 0) {
                    self.rewardScroller.scrollToPage('prev', 0);
                }
                return false;
            });
            this.e.find('#scroller_btn_right').unbind('touchend').bind('touchend',
            function() {
                if (self.rewardScroller && self.rewardScroller.pagesX && self.rewardScroller.pagesX.length > 0) {
                    self.rewardScroller.scrollToPage('next', 0);
                }
                return false;
            });

            //领取按钮
            this.e.find('#newpassreward_scroller').undelegate('touchend').delegate('.lotteryenablebtn', 'touchend', function(e) {
                var id = $(this).attr('uid');
                var render = function(res) {
                    switch(parseInt(res.status)) {
                        case 1:
                            self.updateListInfo(res, id);
                            break;
                        default:
                            xGame.toast.show(res.msg);
                            break;
                    }
                };

                loadContents({
                    'c': 'map',
                    'm': 'get_newreward',
                    'id': id
                }, render);
                render = null;
            });

            this.e.show();
            $('#worldloading').hide();

        },

        // 显示玩家获得的奖励
        showMessage: function (reward) {
            
            var type = parseInt(reward.re_type);
            xGame.toast.show(lang('reward_success'));

            switch (type) {
                case 1:     // 元宝
                    if (reward.all_gold) {
                        player.updateinfo({'gold': reward.all_gold});
                    } else {
                        var gold = parseInt(player.info.gold) + parseInt(reward.re_num);
                        player.updateinfo({'gold': gold});
                    }
                    break;
                case 2:     // 银币
                    var silver = parseInt(player.info.silver) + parseInt(reward.re_num);
                    player.updateinfo({'silver': silver});
                    break;
                default :
                    break;
            }
        },

        getHtml: function (list) {
            var html = '';
            var shili_name = '';
            for (var i = 0, len = list.length; i < len; i++) {
                shili_name = map_ini['world'][list[i].levelid].name;
                break;
            }

            this.e.find('.passreward_text').find('em').html(shili_name);
           
            for (var i = 0, len = list.length; i < len; i++) {
                if (len <= 4) {
                    this.e.find('#scroller_btn_left').hide();
                    this.e.find('#scroller_btn_right').hide();
                } else {
                    this.e.find('#scroller_btn_left').show();
                    this.e.find('#scroller_btn_right').show();
                }
                html += '<li>';
                var item = list[i];
                
                html += '<div class="box type'+ item.re_type +'">';
                
                html += '<em class="value">x' + item.re_num + '</em>';

                //计算获得显示几行
                if (item.mname.length > 4) {
                    html += '<em class="text double">'+lang('newpassrewardpop_text1')+item.mname+lang('newpassrewardpop_text2')+'</em>';
                } else {
                    html += '<em class="text single">'+lang('newpassrewardpop_text1')+item.mname+lang('newpassrewardpop_text2')+'</em>';
                }

                if (parseInt(item.re_type) == 12) {//武将
                    html += '<span class="img" style="background-image:url(' + xGame.avatar(item.general.image) + ')"></span>';
                } else if (parseInt(item.re_type) == 8 || parseInt(item.re_type) == 30) { //装备
                    html += '<span class="img" style="background-image:url('+ xGame.itemurl(item.info.image) +');background-size:80px;"><em class="name bg" style="color:'+ helper.getItemColor(item.info.quality) +';">' + item.re_name + '</em></span>';
                    if (parseInt(item.re_type) == 30) {
                        html += '<span class="itempart"></span>';
                    }
                } else if (parseInt(item.re_type) >= 17 && parseInt(item.re_type) <= 25) {//精华
                    html += '<span class="img" style="background-image:url('+ xGame.essence(item.id) +');"><em class="name bg">'+ item.name +'</em></span>';
                } else if (parseInt(item.re_type) == 29) {//天赋卷轴
                    html += '<span class="img" style="background-image:url('+ ximg(staticUrl + 'img/pop/talent_item' + item.info.itemtype + '.png') +');background-size:80px;"><em class="name bg" style="color:'+ helper.getItemColor(item.info.quality) +';">' + item.re_name + '</em></span>';
                } else {
                    html += '<span class="img"></span>';
                    html += '<em class="name bg">' + item.re_name + '</em>';
                }

                if (parseInt(item.is_get) != 0) {
                    if (parseInt(item.is_get) == 1) {
                        html += '<button class="lotteryenablebtn" id="btn'+item.id+'" uid="'+item.id+'">'+lang('receive')+'</button>';
                    } else if (parseInt(item.is_get) == 2) {
                        html += '<button class="lotterydisbtn" id="btn'+item.id+'" uid="'+item.id+'">'+lang('warena_have_fetch')+'</button>';
                    }
                } else {
                    html += '<button class="lotterydisbtn" id="btn'+item.id+'" uid="'+item.id+'">'+lang('not_reached')+'</button>';
                }

                html += '</div></li>';
            }

            return html;
        },

        updateListInfo: function(res, id) {
            //刷新领取按钮
            this.e.find('.box').find('#btn'+id).removeClass('lotteryenablebtn').addClass('lotterydisbtn').html(lang('warena_have_fetch'));
            //显示获取奖励
            this.showMessage(res.reward);
            //刷新通关奖励叹号
            if(res.popo == 0) {
                $('#header').find('#menu_newpassreward').find('.popo').hide();
            }
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#newpassreward_hide').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
        },

        hide: function() {
            if (this.rewardScroller) {
                this.rewardScroller.destroy();
                this.rewardScroller = null;
            }
            this.unbindAll();
        },
    };
})();