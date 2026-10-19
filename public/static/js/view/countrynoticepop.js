/*国家战报*/
(function() {
    countrynoticePopClass = function() {
        this.e;
        this.isshow = false;
        this.honorDom; //荣誉战报
        this.disgraceDom; //屈辱战报
        this.pageBtns;
        this.cType = 0; //列表类型
        this.page = 1; //当前页
        this.honorpages = 1; //荣誉总页数
        this.disgracepages =1; //屈辱总页数
    };

    countrynoticePopClass.prototype = {
        init: function() {
            var that = this;

            if(that.isshow) {
                $('#worldloading').hide();
                return;
            }

            if(that.e != undefined) {
                return false;
            }

            that.requestList(0, 1, true);//首次请求屈辱战报的第一页

        },

        // 战报请求
        requestList: function(type, page, first) {
            var that = this;
            var initRender = function(res) {
                if (res.status == 1) {
                    $('#ajaxloading').hide();
                    
                    if (first) {
                        that.initPage();
                    }
                    that.fillList(res.log, res.total, type, page);
                } else if (res.status == 2) {
                    $('#ajaxloading').hide();

                    xGame.toast.show(lang('countrynoticepop_not_notice'));
                }  else {
                    $('#ajaxloading').hide();
                    
                    xGame.toast.show(lang('undefined_error'));
                }
                $('#worldloading').hide();
            };

            loadContents({
                'c': 'country',
                'm': 'fight_notice',
                'type': type,
                'page': page
            },
            initRender, true);
            initRender = null;
        },

        initPage: function() {
            var that = this;

            that.e = xGame.loadPopTmpl('countrynotice');
            that.e.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                countrynoticepop.hide();
                e.preventDefault();
            });

            that.honorDom = that.e.find('#honor_list');
            that.disgraceDom = that.e.find('#disgrace_list');
            that.pageBtns = that.e.find('#notice_page_btns');
            that.bandAll();
            that.e.show();
            that.isshow = true;
            $('#worldloading').hide();
        },

        //填充战报列表
        fillList: function(log, total, type, page) {
            var that = this;
            var html = '';
            var numPage = log.length;
            var date;

            var pages = Math.ceil(total / 4);
            pages = pages == 0 ? 1 : pages;
            type == 1 ? that.honorpages = pages : that.disgracepages = pages;

            if(log != '' && log != undefined) {
                for(var i = 0; i < numPage; i++) {
                    html += '<li class="list">';

                    var stype = log[i].type == 0 ? 'stone' : 'ship';//stone是抢矿，ship是抢船
                    var nickname = log[i].attack_nickname == player.info.nickname ? lang('you') : log[i].attack_nickname;
                    var member1_name = log[i].member1_name == player.info.nickname ? lang('you') : log[i].member1_name;
                    if(log[i].member2_name) {
                        var member2_name = log[i].member2_name == player.info.nickname ? lang('you') : log[i].member2_name;
                    }
                    
                    html += '<span class="' + stype +'"></span>';
                    if(type == 0) {     //屈辱战报
                        html += '<p><em class="red">【' + log[i].attack_country_name + '】</em>';
                        html += '<em uid="' + log[i].attack + '" class="red player_info">' + nickname + ' </em> ' + lang('countrynoticepop_' + stype);

                    } else {            //荣誉战报
                        html += '<p><em uid="' + log[i].attack + '" class="red player_info">' + nickname + '</em>' + lang('countrynoticepop_' + stype);
                        html += '<em class="red">【' + log[i].member_country_name + '】</em>';
                    }
                    if(log[i].type == 0) {          //抢矿
                        html += lang('countrynoticepop_mine', [log[i].member1, member1_name]);
                    } else if(log[i].type == 1) {   //打劫贸易
                        if(member2_name) {
                            html += lang('countrynoticepop_trade1', [log[i].member1, member1_name, log[i].member2, member2_name]);
                            member2_name = null;
                        } else {
                            html += lang('countrynoticepop_trade2', [log[i].member1, member1_name]);
                        }
                    }
                    html += '</p><em class="date">' + log[i].dateline + '</em>';
                    html += '</li>';
                }
            }

            that.pageBtns.find('#notice_page_no').html(page);
            that.cType = type;
            if(type == 1) {
                that.disgraceDom.hide();
                that.honorDom.show();
                that.e.find('#honor_list').attr('pid', page);
                that.honorDom.find('ul').html(html);
            } else {                
                that.honorDom.hide();
                that.disgraceDom.show();
                that.e.find('#disgrace_list').attr('pid', page);
                that.disgraceDom.find('ul').html(html);
            }
        },

        bandAll: function() {
            var that = this;
            var bandPage;

            /*绑定战报按钮*/
            that.e.undelegate('.notice_btn', 'touchend').delegate('.notice_btn', 'touchend', 
            function() {
                $('#ajaxloading').show();
                if (xGame.checkLock(true)  || that.cType == $(this).attr('pid')) {
                    $('#ajaxloading').hide();
                    return false;
                }
                var type = $(this).attr('pid');
                $(this).siblings().attr('class', 'notice_btn disable');
                $(this).attr('class', 'notice_btn');
                if(type == 0) {
                    that.page = parseInt(that.e.find('#disgrace_list').attr('pid'));
                } else {
                    that.page = parseInt(that.e.find('#honor_list').attr('pid'));
                }
                that.requestList(type, that.page);
            });

            /*绑定玩家资料按钮*/
            that.e.find('#countrynotice_main').undelegate('.player_info', 'touchend').delegate('.player_info', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                xGame.showpop('info', parseInt($(this).attr('uid')));
            });

            // 页码按钮绑定
            that.pageBtns.undelegate('.btn', 'touchend').delegate('.btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }

                var btntype = parseInt($(this).attr('pid'));
                var type = that.cType;
                if(type == 0) {
                    that.page = parseInt(that.e.find('#disgrace_list').attr('pid'));
                    var allpage = that.disgracepages;
                } else {
                    that.page = parseInt(that.e.find('#honor_list').attr('pid'));
                    var allpage = that.honorpages;
                }

                if(btntype == 1) {              //第一页
                    if(that.page == 1) {return false;}
                    $('#ajaxloading').show();
                    that.requestList(type, 1);
                } else if(btntype == 2) {       //上一页
                    if(that.page == 1) {return false;}
                    $('#ajaxloading').show();
                    that.page = that.page - 1 > 0 ? that.page - 1 : 1;
                    that.requestList(type, that.page);
                } else if(btntype == 3) {       //下一页
                    if (that.page == allpage) {return false};
                    $('#ajaxloading').show();
                    that.page = that.page + 1 < allpage ? that.page + 1 : allpage;
                    that.requestList(type, that.page);
                } else {                        //最后页
                    if (that.page == allpage) {return false};
                    $('#ajaxloading').show();
                    that.requestList(type, allpage);
                }
            });
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.hidebtn').unbind('touchend');
                this.e.undelegate('.notice_btn', 'touchend');
                this.e.find('#countrynotice_main').undelegate('.player_info', 'touchend');
            }
            if (this.pageBtns) {
                this.pageBtns.undelegate('.btn', 'touchend')
                this.pageBtns.remove();
                this.pageBtns = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            if (this.honorDom) {
                this.honorDom.remove();
                this.honorDom = null;
            }
            if (this.disgraceDom) {
                this.disgraceDom.remove();
                this.disgraceDom = null;
            }
            this.honorpages = null;
            this.disgracepages = null;
            this.unbindAll();
            this.e.remove();
            this.e = null;
        }
    }
})();