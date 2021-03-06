(function(global,agh){
  var lwiki_empty_time=-1;
  function lwiki_benchmark(func,elemResult){
    if(lwiki_empty_time<0){
      lwiki_empty_time=0.0;
      lwiki_empty_time=lwiki_benchmark(function(){});
    }

    for(var weight=1;weight<1e8;weight<<=1){
      var t0=new Date().getTime();
      for(var i=0;i<weight;i++)func();
      var dt=new Date().getTime()-t0;
      if(dt>250)break;
    }

    var time=dt/weight-lwiki_empty_time;
    if(time<0.0)time=0.0;
    if(elemResult){
      var result;
      if(time<1e-3)
        result=time*1e6+" nsec";
      else if(time<1)
        result=time*1e3+" usec";
      else if(time<1000)
        result=time+" msec";
      else
        result=time*1e-3+" sec";
      agh.dom.setInnerText(elemResult,result);
    }
    return time;
  }

  function initialize_runjs(pre){
    var line=document.createElement('div');
    line.style.marginTop='0';
    pre.style.marginBottom='0';

    try{
      var func=new Function(agh.dom.getInnerText(pre));

      var btnRun=document.createElement('button');
      agh.dom.setInnerText(btnRun,"Run");
      agh.addEventListener(btnRun,"click",func);
      line.appendChild(btnRun);

      var btnBench=document.createElement('button');
      agh.dom.setInnerText(btnBench,"Benchmark");
      agh.addEventListener(btnBench,"click",function(){
        lwiki_benchmark(func,lblBench);
      });
      line.appendChild(btnBench);

      var lblBench=document.createElement('span');
      lblBench.style.marginLeft='0.2em';
      line.appendChild(lblBench);

      agh.dom.insert(pre,line,'after');
    }catch(ex){}
  }
  function initialize_runhtm(pre){
    var e_line=document.createElement('div');
    e_line.style.marginTop='0';
    e_line.style.marginBottom='0';
    pre.style.marginBottom='0';
    agh.dom.insert(pre,e_line,'after');

    var e_ret=document.createElement('div');
    e_ret.className="lwiki-run-htm";
    e_ret.style.display='none';
    e_ret.style.marginTop='0';
    e_ret.style.border='1px solid gray';
    e_ret.style.backgroundColor='white';
    agh.dom.insert(e_line,e_ret,'after');

    var text=agh.dom.getInnerText(pre);
    var btnShow=document.createElement('button');
    agh.dom.setInnerText(btnShow,"Show");
    agh.addEventListener(btnShow,"click",function(){
      e_ret.innerHTML=text;
      e_ret.style.display='block';
    });
    e_line.appendChild(btnShow);
  }

  function initialize_toggle(div, closed) {
    if (div.initialize_toggle_processed) return;
    div.initialize_toggle_processed = true;

    var e_container = div.ownerDocument.createElement('div');
    var arr = agh(div.childNodes);
    for (var i = 0; i < arr.length; i++)
      e_container.appendChild(arr[i]);

    var e_title = div.ownerDocument.createElement('span');
    div.appendChild(e_title);
    div.appendChild(e_container);
    e_title.style.cursor = 'pointer';
    e_title.style.textDecoration = 'underline';
    e_title.style.userSelect = 'none';

    var title = 'Details';
    var lwiki_title = div.dataset.lwikiTitle;
    if (lwiki_title != null && lwiki_title != '')
      title = lwiki_title;

    function update_state() {
      if (closed) {
        e_container.style.display = 'none';
        e_title.innerHTML = '▶ ' + title;
      } else {
        e_container.style.display = 'block';
        e_title.innerHTML = '▼ ' + title;
      }
    }
    agh.addEventListener(e_title, 'click', function() {
      closed = !closed;
      update_state();
    });
    update_state();
  }

  function initialize_color(target) {
    var m;
    if (!(m = (target.className || "").match(/(?:^|\s)lwiki-language-([^\s]+)(?:\s|$)/))) return;
    var language = m[1];

    var content = target.innerHTML;
    var langs = language.split('/');
    for (var j = 0; j < langs.length; j++) {
      var lang = langs[j];
      if (lang == 'iline') lang = '.iline';
      if (agh.Text.Color[lang] instanceof Function)
        content = agh.Text.Color(content, lang, "/html");
    }
    target.innerHTML = content;
  }

  function lwikiModifyContent(target){
    if(!target)target=document;
    var pres=target.getElementsByTagName("pre");
    for(var i=0;i<pres.length;i++){
      var pre=pres[i];
      if((/(?:^|\s)lwiki-run-js(?:\s|$)/).test(pre.className))
        initialize_runjs(pre);
      else if((/(?:^|\s)lwiki-run-htm(?:\s|$)/).test(pre.className))
        initialize_runhtm(pre);

      initialize_color(pre);
    }

    var divs=target.getElementsByTagName("div");
    for(var i=0;i<divs.length;i++){
      var div=divs[i];
      if((/(?:^|\s)lwiki-toggle-closed(?:\s|$)/).test(div.className))
        initialize_toggle(div,true);
      else if((/(?:^|\s)lwiki-toggle-opened(?:\s|$)/).test(div.className))
        initialize_toggle(div,false);
    }

    var codes=target.getElementsByTagName("code");
    for(var i=0;i<codes.length;i++)
      initialize_color(codes[i]);
  }

  //***************************************************************************
  //
  //  プレビュー更新を ajax で行う様に細工する
  //
  //  プレビューは既定では submit で処理され、画面全体の更新を引き起こす。
  //  スクリプトが有効な環境ではプレビュー内容のみを更新する様に動作を変更する。
  //
  //---------------------------------------------------------------------------
  function _encode(content){
    if(window.encodeURIComponent)
      return encodeURIComponent(content);
    // else if(window.encodeURI)
    //   return encodeURI(content);
    // else if(window.escape)
    //   return escape(content);
    else{
      return content.replace(/[^\w]/g,function($0){
        var code=$0.charCodeAt(0);
        if(code===0x20)return '+';

        var hex=code.toString(16);
        if(code<0x10)
          return "%0"+hex;
        else if(code<0x100)
          return "%"+hex;
        else if(code<0x1000)
          return "%u0"+hex;
        else
          return "%u"+hex;
      });
    }
  }

  function initialize_preview(){
    var form=document.getElementById("lwiki_form_edit");
    if(!form)return;

    var txtWiki=form.elements["content"];
    var btnPrev=form.elements["page_preview"];
    var e_output=document.getElementById("lwiki_page_preview");
    var e_head=document.getElementById("lwiki_page_preview_head");
    agh.addEventListener(btnPrev,"click",function(e){
      var xhr=new agh.XMLHttpRequest();
      xhr.open("POST","index.php?mode=convert",true);
      xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
      xhr.onreadystatechange=function(){
        if(xhr.readyState===4&&xhr.status===200){
          agh.dom.remove(e_head);

          // 挿入&修飾
          e_output.innerHTML=xhr.responseText;
          lwikiModifyContent(e_output);
          if(agh.fly&&agh.fly.processContents)
            agh.fly.processContents(e_output);

          agh.dom.insert(e_output,e_head,'begin');
        }
      };
      xhr.send("content="+_encode(txtWiki.value));

      // cancel default behavior
      if(e.preventDefault)e.preventDefault();
      return e.returnValue=false;
    },true);
  }

  function initialize_comment_preview(){
    var div=window.lwiki.commentFormDiv;
    var form=window.lwiki.commentForm;
    if(!form)return;

    if(form.m_lwikiPreviewInitialized)return;
    form.m_lwikiPreviewInitialized=true;

    var e_prev=document.createElement("div");
    e_prev.className="comment-body lwiki-comment-preview";
    e_prev.style.display="none";
    agh.dom.insert(div,e_prev,'begin');

    var inTxt=form.elements["body"];
    var inPost=form.elements["comment_post"];

    var btnPrev=document.createElement("input");
    btnPrev.type="button";
    btnPrev.value="プレビュー";
    agh.addEventListener(btnPrev,"click",function(e){
      var xhr=new agh.XMLHttpRequest();
      xhr.open("POST","index.php?mode=convert",true);
      xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
      xhr.onreadystatechange=function(){
        if(xhr.readyState===4&&xhr.status===200){
          // 挿入&修飾
          e_prev.style.display="block";
          e_prev.innerHTML=xhr.responseText;
          lwikiModifyContent(e_prev);
          if(agh.fly&&agh.fly.processContents)
            agh.fly.processContents(e_prev);
        }
      };
      xhr.send("content="+_encode(inTxt.value));
    });
    agh.dom.insert(inPost,btnPrev,'before');
  }

  function getElementsByTagNameAndClassName(parentNode,tagName,className){
    if(parentNode.querySelectorAll){
      return agh(parentNode.querySelectorAll(tagName+'.'+className),Array);
    }else{
      var ret=[];
      var elems=parentNode.getElementsByTagName(tagName);
      var holders=[];
      for(var i=0,n=elems.length;i<n;i++){
        var elem=elems[i];
        if(elem.className===className)
          ret.push(elem);
      }
      return ret;
    }
  }

  function initialize_comment_list(){
    var lwiki=window.lwiki;
    if(!lwiki.commentForm)return;
    var inTxt=lwiki.commentForm.elements["body"];

    lwiki.commentInsertAnchor=function(commentIndex){
      inTxt.value='>>'+commentIndex+'\n'+inTxt.value.replace(/^>>\d+\s*\n?/,"");
    };
    lwiki.commentSource=[];
    lwiki.commentInsertQuotes=function(commentIndex){
      var source=lwiki.commentSource[commentIndex];
      if(source){
        var quoted=source.replace(/^|\n(?!$)/g,'$&> ').replace(/\n?$/,'\n');
        inTxt.value=inTxt.value.replace(/(.)\n?$/,'$1\n\n')+quoted;
      }
    };

    // div.comment-holder
    var holders=getElementsByTagNameAndClassName(document,'div','comment-holder');

    var index2info=[];
    for(var i=0,n=holders.length;i<n;i++){
      var div=holders[i];
      var p=div.childNodes[0];
      var commentIndex=parseInt(p.getAttribute('data-comment-number'));

      // [返信] ボタン
      var reply='lwiki.commentInsertAnchor('+commentIndex+');';
      var html=' <button class="lwiki-comment-button" onclick="'+reply+'">返信</button>';

      // [引用] ボタン
      var source=div.getAttribute('data-comment-source');
      if(source){
        lwiki.commentSource[commentIndex]=source;
        var quote='lwiki.commentInsertQuotes('+commentIndex+');';
        html+=' <button class="lwiki-comment-button" onclick="'+quote+'">引用</button>';
      }

      agh.dom.insert(p,html,'end');

      //
      // コメント並び替え
      //

      var rootAnchor=(function determineRootAnchor(){
        var cand=null;
        var anchors=getElementsByTagNameAndClassName(div,'a','lwiki-comment-anchor');
        for(var j=0,m=anchors.length;j<m;j++){
          var anchor=parseInt(agh.dom.getInnerText(anchors[j]));
          if(!isNaN(anchor)&&0<anchor&&anchor<commentIndex&&anchor in index2info){
            var aroot=index2info[anchor].rootAnchor;
            if(cand===null){
              cand=aroot;
            }else if(cand!==aroot){
              return commentIndex;
            }
          }
        }

        if(cand===null)
          return commentIndex;
        return cand;
      })();

      if(rootAnchor!==commentIndex)
        agh.dom.insert(index2info[rootAnchor].div,div,'end');

      index2info[commentIndex]={div:div,p:p,rootAnchor:rootAnchor};
    }
  }

  //---------------------------------------------------------------------------

  agh.scripts.wait(["event:onload","agh.dom.js","agh.text.color.js"],function(){
    agh.Namespace('lwiki');
    var div=document.getElementById("comment-form");
    if(div){
      window.lwiki.commentFormDiv=div;
      var form=div.getElementsByTagName("form")[0];
      if(form)window.lwiki.commentForm=form;
    }

    lwikiModifyContent(document);
    initialize_preview();
    initialize_comment_preview();
    initialize_comment_list();
  });
})(window,window.agh);
