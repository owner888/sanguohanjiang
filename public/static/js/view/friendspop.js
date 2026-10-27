/* 好友 */
(function() {
    friendsPopClass = function() {
        this.e;
        this.isshow = false;
        
        this.friends_num;
        this.friends_tnum;
        this.friends_olnum;
        this.page;
        this.pages;
        this.pageDom;
        this.mainDom;
 
    };
    friendsPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var initRender = function(res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.initPage(res);
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }

                if (res.status != 1) {
                    $('#worldloading').hide();
                }
            };
            
            loadContents({
                'c': 'friend',
                'm': 'index',
                'p': '1',
                'l': '4'
            },
            initRender, true);
            initRender = null;
            
        },
        
        //设置页面
        initPage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('friends');
            
            that.e.find('.content').find('#friends_hide').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                friendspop.hide();
                e.preventDefault();
                e.stopPropagation();
            });
            
            that.pageDom = that.e.find('#friends_page_btns');
            that.mainDom = that.e.find('#friends_main');
            
            that.bindAll();
            
            that.friends_num = (res.now_number != null) ? res.now_number : (res.list ? res.list.length : 0);
            that.friends_tnum = (res.all_number != null) ? res.all_number : 100;
            that.friends_olnum = (res.online != null) ? res.online : that.friends_num;
            that.page = parseInt(res.page) || 1;
            that.pages = parseInt(res.all_page) || 1;
            
            that.showFriendsNum();
            that.showOlNum();
            that.showPageNum();
            
            that.buildMember(res.list || []);
            
            that.e.show();
            $('#worldloading').hide();
            
        },
        
        buildMember: function(list) {
            var html = '';
            list = list || [];
            for (var i = 0;i < 4;i ++) {
               
                if (list[i] == undefined) {
                    html += '<li class="offline">';
                } else {
                    var f = list[i];
                    var fNick = f.nickname || f.name || ('好友' + (f.fuid || f.uid || (i + 1)));
                    var fCountry = f.country || '魏国';
                    var fJob = f.job || '偏将军';
                    var fUid = f.fuid || f.uid || 0;

                    if (f.online == 1) {
                        html += '<li class="online">';
                    } else {
                        html += '<li class="offline">';
                    }
                    html += '<div>';
                    html += '<span class="userinfo" uid="' + fUid + '"></span>';
                    if (typeof f.tree != 'undefined' && parseInt(f.tree) > 1) {
                        html += '<p class="p1 tree' + f.tree + '">' + fNick + ' Lv.' + (f.level || 1) + '</p>';
                    } else {
                        html += '<p class="p1">' + fNick + ' Lv.' + (f.level || 1) + '</p>';
                    }
                    if (f.countryid == 0) {
                        html += '<p class="p2"></p>';
                    } else {
                        html += '<p class="p2">' + fCountry + ' 【' + fJob + '】</p>';
                    }
                    
                    html += '<span class="status"></span>';
                    html += '<span class="close" uid="' + fUid + '" name="' + fNick + '"></span>';
                     html += '</div>';
                }
                html += '<b>' + (1 + i + 4 * (this.page - 1)) + '</b>';
                html += '</li>';
            }
            this.mainDom.html(html);
            html = null;
        },
        
        showFriendsNum: function() {
            this.e.find('#friends_num').html(this.friends_num + '/' + this.friends_tnum);
        },
        
        showOlNum: function() {
            this.e.find('#friends_ol_num').html(this.friends_olnum);
        },
        
        showPageNum: function() {
            this.pageDom.find('#friends_page_no').html(this.page);
        },
        
        bindAll: function(){
            var that = this;

            that.pageDom.undelegate('#friends_page_btn1', 'touchend').delegate('#friends_page_btn1', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.page == 1) {
                    return false;
                }
                that.showByPage(1);
            });
            
            that.pageDom.undelegate('#friends_page_btn2', 'touchend').delegate('#friends_page_btn2', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.page == 1) {
                    return false;
                }
                
                that.showByPage(that.page - 1);
            });
            
            that.pageDom.undelegate('#friends_page_btn3', 'touchend').delegate('#friends_page_btn3', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.page == that.pages) {
                    return false;
                }
                that.showByPage(that.page + 1);
            });
            
            that.pageDom.undelegate('#friends_page_btn4', 'touchend').delegate('#friends_page_btn4', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.page == that.pages) {
                    return false;
                }
                that.showByPage(that.pages);
            });
            
            that.mainDom.undelegate('.close', 'touchend').delegate('.close', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var uid = $(this).attr('uid');
                
                xGame.confirm.show(lang('friends_delete_friend'),lang('friends_delete_friend_ask', [$(this).attr('name')]),2,
                function() {
                    that.deleteFunc(uid);
                    xGame.confirm.hide();
                },
                function() {
                    xGame.confirm.hide();
                },
                lang('friends_delete'), null);
            });
            
            that.mainDom.undelegate('.userinfo', 'touchend').delegate('.userinfo', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.showpop('info', parseInt($(this).attr('uid')));
            });
            
            
            
        },
        
        showByPage: function(page) {
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:
                        that.friends_num = res.now_number;
                        that.friends_tnum = res.all_number;
                        that.friends_olnum = res.online;
                        that.page = res.page;
                        that.pages = res.all_page;
                        
                        that.showFriendsNum();
                        that.showOlNum();
                        that.showPageNum();
                        
                        that.buildMember(res.list);
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        xGame.unlock();
                        break;
                }
            };
             
            loadContents({
                'c': 'friend',
                'm': 'index',
                'p': page,
                'l' : '4'
            },
            Func);
            Func = null;
        },
        
        deleteFunc: function(uid){
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:
                        xGame.toast.show(lang('friends_delete_sucess'));
                        
                        that.friends_num = res.now_number;
                        that.friends_tnum = res.all_number;
                        that.friends_olnum = res.online;
                        that.page = res.page;
                        that.pages = res.all_page;
                        
                        that.showFriendsNum();
                        that.showOlNum();
                        that.showPageNum();
                        
                        that.buildMember(res.list);
                        break;
                    case -2:
                        xGame.toast.show(lang('friends_delete_fail'));
                        xGame.unlock();
                        break;
                    case -1:
                        xGame.toast.show(lang('friends_not_friend_to_delete'));
                        xGame.unlock();
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        xGame.unlock();
                        break;
                }
            };
             
            loadContents({
                'c': 'friend',
                'm': 'del_friend',
                'f': uid,
                'p': that.page,
                'l' : '4'
            },
            Func);
            Func = null;
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.content').find('#friends_hide').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.pageDom) {
                this.pageDom.undelegate('#friends_page_btn1', 'touchend');
                this.pageDom.undelegate('#friends_page_btn2', 'touchend');
                this.pageDom.undelegate('#friends_page_btn3', 'touchend');
                this.pageDom.undelegate('#friends_page_btn4', 'touchend');
                this.pageDom.remove();
                this.pageDom = null;
            }
            if (this.mainDom) {
                this.mainDom.undelegate('.close', 'touchend');
                this.mainDom.undelegate('.userinfo', 'touchend');
                this.mainDom.remove();
                this.mainDom = null;
            }
        },
        
        hide: function() {

            xGame.unlock();

            this.isshow = false;         
            this.friends_num = null;
            this.friends_tnum = null;
            this.friends_olnum = null;
            this.page = null;
            this.pages = null;
            this.unbindAll();   
            this.heroinfo = null;
            
        },
    };
})();