(function($){$.FroalaEditor.PLUGINS.mediaManager=function(editor){function onInsertFile(){new $.wn.mediaManager.popup({alias:'ocmediamanager',cropAndInsertButton:false,onInsert:function(items){if(!items.length){$.wn.alert($.wn.lang.get('mediamanager.invalid_file_empty_insert'))
return}if(items.length>1){$.wn.alert($.wn.lang.get('mediamanager.invalid_file_single_insert'))
return}var link,text=editor.selection.text(),textIsEmpty=$.trim(text)===''
for(var i=0,len=items.length;i<len;i++){var text=textIsEmpty?items[i].title:text
link=items[i].publicUrl}editor.events.focus(true);editor.selection.restore();editor.html.insert('<a href="'+link+'" id="fr-inserted-file" class="fr-file">'+text+'</a>');var $file=editor.$el.find('#fr-inserted-file');$file.removeAttr('id');editor.undo.saveStep()
this.hide()}})}function onInsertImage(){var $currentImage=editor.image.get(),selection=editor.selection.get(),range=editor.selection.ranges(0);new $.wn.mediaManager.popup({alias:'ocmediamanager',cropAndInsertButton:true,onInsert:function(items){editor.selection.clear();selection.addRange(range);if(!items.length){$.wn.alert($.wn.lang.get('mediamanager.invalid_image_empty_insert'))
return}var imagesInserted=0
for(var i=0,len=items.length;i<len;i++){if(items[i].documentType!=='image'){$.wn.alert($.wn.lang.get('mediamanager.invalid_image_invalid_insert','The file "'+items[i].title+'" is not an image.'))
continue}editor.image.insert(items[i].publicUrl,false,{},$currentImage)
imagesInserted++
if(imagesInserted==1){$currentImage=null}}if(imagesInserted!==0){this.hide()
editor.undo.saveStep()}}})}function onInsertVideo(){new $.wn.mediaManager.popup({alias:'ocmediamanager',cropAndInsertButton:false,onInsert:function(items){if(!items.length){$.wn.alert($.wn.lang.get('mediamanager.invalid_video_empty_insert'))
return}if(items.length>1){$.wn.alert($.wn.lang.get('mediamanager.invalid_file_single_insert'))
return}var item=items[0]
if(item.documentType!=='video'){$.wn.alert($.wn.lang.get('mediamanager.invalid_video_invalid_insert','The file "'+item.title+'" is not a video.'))
return}var $richEditorNode=editor.$el.closest('[data-control="richeditor"]')
$richEditorNode.richEditor('insertVideo',item.publicUrl,item.title)
this.hide()}})}function onInsertAudio(){new $.wn.mediaManager.popup({alias:'ocmediamanager',cropAndInsertButton:false,onInsert:function(items){if(!items.length){$.wn.alert($.wn.lang.get('mediamanager.invalid_audio_empty_insert'))
return}if(items.length>1){$.wn.alert($.wn.lang.get('mediamanager.invalid_file_single_insert'))
return}var item=items[0]
if(item.documentType!=='audio'){$.wn.alert($.wn.lang.get('mediamanager.invalid_audio_invalid_insert','The file "'+item.title+'" is not an audio file.'))
return}var $richEditorNode=editor.$el.closest('[data-control="richeditor"]')
$richEditorNode.richEditor('insertAudio',item.publicUrl,item.title)
this.hide()}})}function _insertVideoFallback(link){var $richEditorNode=editor.$el.closest('[data-control="richeditor"]')
var title=link.substring(link.lastIndexOf('/')+1)
$richEditorNode.richEditor('insertVideo',link,title)
editor.popups.hide('video.insert')}function _insertAudioFallback(link){var $richEditorNode=editor.$el.closest('[data-control="richeditor"]')
var title=link.substring(link.lastIndexOf('/')+1)
$richEditorNode.richEditor('insertAudio',link,title)
editor.popups.hide('audio.insert')}function _init(){editor.events.on('destroy',_destroy,true)
editor.events.on('video.linkError',_insertVideoFallback)
editor.events.on('audio.linkError',_insertAudioFallback)}function _destroy(){}return{_init:_init,insertFile:onInsertFile,insertImage:onInsertImage,insertVideo:onInsertVideo,insertAudio:onInsertAudio}}
if(!$.FE.PLUGINS.link||!$.FE.PLUGINS.file||!$.FE.PLUGINS.image||!$.FE.PLUGINS.video){throw new Error('Media manager plugin requires link, file, image and video plugin.');}$.FE.DEFAULTS.imageInsertButtons.push('mmImageManager');$.FE.RegisterCommand('mmImageManager',{title:'Browse',undo:false,focus:false,callback:function(){this.mediaManager.insertImage();},plugin:'mediaManager'})
$.FE.DefineIcon('mmImageManager',{NAME:'folder'});$.FE.DEFAULTS.fileInsertButtons.push('mmFileManager');$.FE.RegisterCommand('mmFileManager',{title:'Browse',undo:false,focus:false,callback:function(){this.mediaManager.insertFile();},plugin:'mediaManager'})
$.FE.DefineIcon('mmFileManager',{NAME:'folder'});$.FE.DEFAULTS.videoInsertButtons.push('mmVideoManager');$.FE.RegisterCommand('mmVideoManager',{title:'Browse',undo:false,focus:false,callback:function(){this.mediaManager.insertVideo();},plugin:'mediaManager'})
$.FE.DefineIcon('mmVideoManager',{NAME:'folder'});$.FE.DEFAULTS.audioInsertButtons.push('mmAudioManager');$.FE.RegisterCommand('mmAudioManager',{title:'Browse',undo:false,focus:false,callback:function(){this.mediaManager.insertAudio();},plugin:'mediaManager'})
$.FE.DefineIcon('mmAudioManager',{NAME:'folder'});})(jQuery);var richeditorPageLinksPlugin
function richeditorPageLinksSelectPage($form){richeditorPageLinksPlugin.setLinkValueFromPopup($form)}$.FroalaEditor.DEFAULTS=$.extend($.FroalaEditor.DEFAULTS,{pageLinksHandler:'onLoadPageLinksForm'});$.FroalaEditor.DEFAULTS.key='JA6B2B5A1qB1F1F4D3I1A15A11D3E6B5dVh1VCQWa1EOQFe1NCb1==';(function($){$.FroalaEditor.PLUGINS.pageLinks=function(editor){function setLinkValueFromPopup($form){var $select=$('select[name=pagelink]',$form)
var link={text:$('option:selected',$select).text().trim(),href:$select.val()}
setTimeout(function(){editor.popups.show('link.insert')
setLinkValue(link)},300)}function setLinkValue(link){var $popup=editor.popups.get('link.insert');var text_inputs=$popup.find('input.fr-link-attr[type="text"]');var check_inputs=$popup.find('input.fr-link-attr[type="checkbox"]');var $input;var i;for(i=0;i<text_inputs.length;i++){$input=$(text_inputs[i]);var name=$input.attr('name');var value=link[name];if(name==='text'){if($input.val().length===0){$input.val(value);}}else{$input.val(value);}}for(i=0;i<check_inputs.length;i++){$input=$(check_inputs[i]);$input.prop('checked',$input.data('checked')==link[$input.attr('name')]);}editor.selection.restore();}function insertLink(){richeditorPageLinksPlugin=this
editor.$el.popup({handler:editor.opts.pageLinksHandler}).one('shown.oc.popup.pageLinks',function(){editor.selection.save()})}function _init(){}return{_init:_init,setLinkValueFromPopup:setLinkValueFromPopup,setLinkValue:setLinkValue,insertLink:insertLink}}
$.FE.DEFAULTS.linkInsertButtons=['linkBack','|','linkPageLinks']
$.FE.RegisterCommand('linkPageLinks',{title:'Choose Link',undo:false,focus:false,callback:function(){this.pageLinks.insertLink()},plugin:'pageLinks'})
$.FE.DefineIcon('linkPageLinks',{NAME:'search'});})(jQuery);(function($){$.FroalaEditor.PLUGINS.figures=function(editor){function insertElement($el){var html=$('<div />').append($el.clone()).remove().html()
editor.events.focus(true)
editor.selection.restore()
editor.html.insert(html)
editor.html.cleanEmptyTags()
$('figure',editor.$el).each(function(){var $this=$(this),$parent=$this.parent('p'),$next=$this.next('p')
if(!!$parent.length){$this.insertAfter($parent)}if(!!$next.length&&$.trim($next.text()).length==0){$next.remove()}})
editor.undo.saveStep()}function _makeUiBlockElement(){var $node=$('<figure contenteditable="false" tabindex="0" data-ui-block="true">&nbsp;</figure>')
$node.get(0).contentEditable=false
return $node}function insertVideo(url,text){var $node=_makeUiBlockElement()
$node.attr('data-video',url)
$node.attr('data-label',text)
insertElement($node)}function insertAudio(url,text){var $node=_makeUiBlockElement()
$node.attr('data-audio',url)
$node.attr('data-label',text)
insertElement($node)}function _initUiBlocks(){$('[data-video], [data-audio]',editor.$el).each(function(){$(this).addClass('fr-draggable').attr({'data-ui-block':'true','draggable':'true','tabindex':'0'}).html('&nbsp;')
this.contentEditable=false})}function _handleUiBlocksKeydown(ev){if(ev.key==='ArrowDown'||ev.key==='ArrowUp'||ev.key==='Backspace'||ev.key==='Delete'){var $block=$(editor.selection.element())
if($block.is('br')){$block=$block.parent()}if(!!$block.length){switch(ev.key){case'ArrowUp':_handleUiBlockCaretIn($block.prev())
break
case'ArrowDown':_handleUiBlockCaretIn($block.next())
break
case'Delete':_handleUiBlockCaretClearEmpty($block.next(),$block)
break
case'Backspace':_handleUiBlockCaretClearEmpty($block.prev(),$block)
break}}}}function _handleUiBlockCaretClearEmpty($block,$p){if($block.attr('data-ui-block')!==undefined&&$.trim($p.text()).length==0){$p.remove()
_handleUiBlockCaretIn($block)
editor.undo.saveStep()}}function _handleUiBlockCaretIn($block){if($block.attr('data-ui-block')!==undefined){$block.focus()
editor.selection.clear()
return true}return false}function _uiBlockKeyDown(ev,block){if(ev.key==='ArrowDown'||ev.key==='ArrowUp'||ev.key==='Enter'||ev.key==='Backspace'||ev.key==='Delete'){switch(ev.key){case'ArrowDown':_focusUiBlockOrText($(block).next(),true)
break
case'ArrowUp':_focusUiBlockOrText($(block).prev(),false)
break
case'Enter':var $paragraph=$('<p><br/></p>')
$paragraph.insertAfter(block)
editor.selection.setAfter(block)
editor.selection.restore()
editor.undo.saveStep()
break
case'Backspace':case'Delete':var $nextFocus=$(block).next(),gotoStart=true
if($nextFocus.length==0){$nextFocus=$(block).prev()
gotoStart=false}_focusUiBlockOrText($nextFocus,gotoStart)
$(block).remove()
editor.undo.saveStep()
break}ev.preventDefault()}}function _focusUiBlockOrText($block,gotoStart){if(!!$block.length){if(!_handleUiBlockCaretIn($block)){if(gotoStart){editor.selection.setAtStart($block.get(0))
editor.selection.restore()}else{editor.selection.setAtEnd($block.get(0))
editor.selection.restore()}}}}function _onKeydown(ev){_handleUiBlocksKeydown(ev)
if(ev.isDefaultPrevented()){return false}}function _onFigureKeydown(ev){if(ev.target&&$(ev.target).attr('data-ui-block')!==undefined){_uiBlockKeyDown(ev,ev.target)}if(ev.isDefaultPrevented()){return false}}function _onSync(html){var $domTree=$('<div>'+html+'</div>')
$domTree.find('[data-video], [data-audio]').each(function(){$(this).removeAttr('contenteditable data-ui-block tabindex draggable').removeClass('fr-draggable fr-dragging')})
return $domTree.html()}function _init(){editor.events.on('initialized',_initUiBlocks)
editor.events.on('html.set',_initUiBlocks)
editor.events.on('html.get',_onSync)
editor.events.on('keydown',_onKeydown)
editor.events.on('destroy',_destroy,true)
editor.$el.on('keydown','figure',_onFigureKeydown)}function _destroy(){editor.$el.off('keydown','figure',_onFigureKeydown)}return{_init:_init,insert:insertElement,insertVideo:insertVideo,insertAudio:insertAudio}}})(jQuery);+function($){"use strict";var Base=$.wn.foundation.base,BaseProto=Base.prototype
var RichEditor=function(element,options){this.options=options
this.$el=$(element)
this.$textarea=this.$el.find('>textarea:first')
this.$form=this.$el.closest('form')
this.editor=null
$.wn.foundation.controlUtils.markDisposable(element)
Base.call(this)
this.init()}
RichEditor.prototype=Object.create(BaseProto)
RichEditor.prototype.constructor=RichEditor
RichEditor.DEFAULTS={linksHandler:null,uploadHandler:null,stylesheet:null,fullpage:false,editorLang:'en',useMediaManager:false,toolbarButtons:null,allowEmptyTags:null,allowTags:null,allowAttributes:null,noWrapTags:null,removeTags:null,lineBreakerTags:null,imageStyles:null,linkStyles:null,paragraphStyles:null,paragraphFormat:null,tableStyles:null,tableCellStyles:null,aceVendorPath:'/',readOnly:false}
RichEditor.prototype.init=function(){var self=this;this.$el.one('dispose-control',this.proxy(this.dispose))
if(!this.$textarea.attr('id')){this.$textarea.attr('id','element-'+Math.random().toString(36).substring(7))}this.initFroala()}
RichEditor.prototype.initFroala=function(){var froalaOptions={editorClass:'control-richeditor',language:this.options.editorLang,fullPage:this.options.fullpage,pageLinksHandler:this.options.linksHandler,uploadHandler:this.options.uploadHandler,aceEditorVendorPath:this.options.aceVendorPath,toolbarSticky:false}
if(this.options.toolbarButtons){froalaOptions.toolbarButtons=this.options.toolbarButtons.split(',')}else{froalaOptions.toolbarButtons=$.wn.richEditorButtons}froalaOptions.imageStyles=this.options.imageStyles?this.options.imageStyles:{'oc-img-rounded':'Rounded','oc-img-bordered':'Bordered'}
froalaOptions.linkStyles=this.options.linkStyles?this.options.linkStyles:{'oc-link-green':'Green','oc-link-strong':'Thick'}
froalaOptions.paragraphStyles=this.options.paragraphStyles?this.options.paragraphStyles:{'oc-text-gray':'Gray','oc-text-bordered':'Bordered','oc-text-spaced':'Spaced','oc-text-uppercase':'Uppercase'}
froalaOptions.paragraphFormat=this.options.paragraphFormat?this.options.paragraphFormat:{'N':'Normal','H1':'Heading 1','H2':'Heading 2','H3':'Heading 3','H4':'Heading 4','PRE':'Code'}
froalaOptions.tableStyles=this.options.tableStyles?this.options.tableStyles:{'oc-dashed-borders':'Dashed Borders','oc-alternate-rows':'Alternate Rows'}
froalaOptions.tableCellStyles=this.options.tableCellStyles?this.options.tableCellStyles:{'oc-cell-highlighted':'Highlighted','oc-cell-thick-border':'Thick'}
froalaOptions.toolbarButtonsMD=froalaOptions.toolbarButtons
froalaOptions.toolbarButtonsSM=froalaOptions.toolbarButtons
froalaOptions.toolbarButtonsXS=froalaOptions.toolbarButtons
if(this.options.allowEmptyTags){froalaOptions.htmlAllowedEmptyTags=[];this.options.allowEmptyTags.split(/[\s,]+/).forEach(function(selector){var tag=selector.split('.',2)
if(froalaOptions.htmlAllowedEmptyTags.indexOf(tag[0])===-1){froalaOptions.htmlAllowedEmptyTags.push(selector)}})}else{froalaOptions.htmlAllowedEmptyTags=['textarea','a','iframe','object','video','style','script','.fa','.fr-emoticon','.fr-inner','path','line','hr','i']}froalaOptions.htmlAllowedTags=this.options.allowTags?this.options.allowTags.split(/[\s,]+/):['a','abbr','address','area','article','aside','audio','b','bdi','bdo','blockquote','br','button','canvas','caption','cite','code','col','colgroup','datalist','dd','del','details','dfn','dialog','div','dl','dt','em','embed','fieldset','figcaption','figure','footer','form','h1','h2','h3','h4','h5','h6','header','hgroup','hr','i','iframe','img','input','ins','kbd','keygen','label','legend','li','link','main','map','mark','menu','menuitem','meter','nav','noscript','object','ol','optgroup','option','output','p','param','pre','progress','queue','rp','rt','ruby','s','samp','script','style','section','select','small','source','span','strike','strong','sub','summary','sup','table','tbody','td','textarea','tfoot','th','thead','time','title','tr','track','u','ul','var','video','wbr']
froalaOptions.htmlAllowedAttrs=this.options.allowAttributes?this.options.allowAttributes.split(/[\s,]+/):['accept','accept-charset','accesskey','action','align','allowfullscreen','allowtransparency','alt','aria-.*','async','autocomplete','autofocus','autoplay','autosave','background','bgcolor','border','charset','cellpadding','cellspacing','checked','cite','class','color','cols','colspan','content','contenteditable','contextmenu','controls','coords','data','data-.*','datetime','default','defer','dir','dirname','disabled','download','draggable','dropzone','enctype','for','form','formaction','frameborder','headers','height','hidden','high','href','hreflang','http-equiv','icon','id','ismap','itemprop','keytype','kind','label','lang','language','list','loop','low','max','maxlength','media','method','min','mozallowfullscreen','multiple','muted','name','novalidate','open','optimum','pattern','ping','placeholder','playsinline','poster','preload','pubdate','radiogroup','readonly','rel','required','reversed','rows','rowspan','sandbox','scope','scoped','scrolling','seamless','selected','shape','size','sizes','span','src','srcdoc','srclang','srcset','start','step','summary','spellcheck','style','tabindex','target','title','type','translate','usemap','value','valign','webkitallowfullscreen','width','wrap']
froalaOptions.htmlDoNotWrapTags=this.options.noWrapTags?this.options.noWrapTags.split(/[\s,]+/):['figure','script','style']
froalaOptions.htmlRemoveTags=this.options.removeTags?this.options.removeTags.split(/[\s,]+/):['script','style','base']
froalaOptions.lineBreakerTags=this.options.lineBreakerTags?this.options.lineBreakerTags.split(/[\s,]+/):['figure, table, hr, iframe, form, dl']
froalaOptions.shortcutsEnabled=['show','bold','italic','underline','indent','outdent','undo','redo']
froalaOptions.requestHeaders={'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content'),'X-Requested-With':'XMLHttpRequest'}
var $form=this.$el.closest('form')
var formData={};if($form.length>0){$.each($form.serializeArray(),function(index,field){formData[field.name]=field.value;})}froalaOptions.imageUploadURL=froalaOptions.fileUploadURL=window.location
froalaOptions.imageUploadParam=froalaOptions.fileUploadParam='file_data'
froalaOptions.imageUploadParams=froalaOptions.fileUploadParams=$.extend(formData,{_handler:froalaOptions.uploadHandler,})
var placeholder=this.$textarea.attr('placeholder')
froalaOptions.placeholderText=placeholder?placeholder:''
froalaOptions.height=this.$el.hasClass('stretch')?Infinity:$('.height-indicator',this.$el).height()
if(!this.options.useMediaManager){delete $.FroalaEditor.PLUGINS.mediaManager}$.FroalaEditor.ICON_TEMPLATES={font_awesome:'<i class="icon-[NAME]"></i>',text:'<span style="text-align: center;">[NAME]</span>',image:'<img src=[SRC] alt=[ALT] />'}
this.$textarea.on('froalaEditor.initialized',this.proxy(this.build))
this.$textarea.on('froalaEditor.contentChanged',this.proxy(this.onChange))
this.$textarea.on('froalaEditor.html.get',this.proxy(this.onSyncContent))
this.$textarea.on('froalaEditor.html.set',this.proxy(this.onSetContent))
this.$textarea.on('froalaEditor.paste.beforeCleanup',this.proxy(this.beforeCleanupPaste))
this.$form.on('oc.beforeRequest',this.proxy(this.onFormBeforeRequest))
this.$textarea.froalaEditor(froalaOptions)
this.editor=this.$textarea.data('froala.editor')
this.editor.$box.on('change',function(e){e.stopPropagation()});if(this.options.readOnly){this.editor.edit.off()}this.$el.on('keydown','.fr-view figure',this.proxy(this.onFigureKeydown))
Snowboard.globalEvent("formwidgets.richeditor.init",this)}
RichEditor.prototype.dispose=function(){this.unregisterHandlers()
this.$textarea.froalaEditor('destroy')
this.$el.removeData('oc.richEditor')
this.options=null
this.$el=null
this.$textarea=null
this.$form=null
this.editor=null
BaseProto.dispose.call(this)}
RichEditor.prototype.unregisterHandlers=function(){this.$el.off('keydown','.fr-view figure',this.proxy(this.onFigureKeydown))
this.$textarea.off('froalaEditor.initialized',this.proxy(this.build))
this.$textarea.off('froalaEditor.contentChanged',this.proxy(this.onChange))
this.$textarea.off('froalaEditor.html.get',this.proxy(this.onSyncContent))
this.$textarea.off('froalaEditor.html.set',this.proxy(this.onSetContent))
this.$textarea.off('froalaEditor.paste.beforeCleanup',this.proxy(this.beforeCleanupPaste))
this.$form.off('oc.beforeRequest',this.proxy(this.onFormBeforeRequest))
$(window).off('resize',this.proxy(this.updateLayout))
$(window).off('oc.updateUi',this.proxy(this.updateLayout))
this.$el.off('dispose-control',this.proxy(this.dispose))}
RichEditor.prototype.build=function(event,editor){this.updateLayout()
$(window).on('resize',this.proxy(this.updateLayout))
$(window).on('oc.updateUi',this.proxy(this.updateLayout))
editor.events.on('keydown',this.proxy(this.onKeydown),true)
this.$textarea.trigger('init.oc.richeditor',[this])}
RichEditor.prototype.isCodeViewActive=function(){return this.editor&&this.editor.codeView&&this.editor.codeView.isActive()}
RichEditor.prototype.getElement=function(){return this.$el}
RichEditor.prototype.getEditor=function(){return this.editor}
RichEditor.prototype.getTextarea=function(){return this.$textarea}
RichEditor.prototype.getContent=function(){return this.editor.html.get()}
RichEditor.prototype.setContent=function(html){this.editor.html.set(html)}
RichEditor.prototype.syncContent=function(){this.editor.events.trigger('contentChanged')}
RichEditor.prototype.updateLayout=function(){var $editor=$('.fr-wrapper',this.$el),$codeEditor=$('.fr-code',this.$el),$toolbar=$('.fr-toolbar',this.$el),$box=$('.fr-box',this.$el)
if(!$editor.length){return}if(this.$el.hasClass('stretch')&&!$box.hasClass('fr-fullscreen')){var height=$toolbar.outerHeight(true)
$editor.css('top',height+1)
$codeEditor.css('top',height)}else{$editor.css('top','')
$codeEditor.css('top','')}}
RichEditor.prototype.insertHtml=function(html){this.editor.html.insert(html)
this.editor.selection.restore()}
RichEditor.prototype.insertElement=function($el){this.insertHtml($('<div />').append($el.clone()).remove().html())}
RichEditor.prototype.insertUiBlock=function($node){this.$textarea.froalaEditor('figures.insert',$node)}
RichEditor.prototype.insertVideo=function(url,title){this.$textarea.froalaEditor('figures.insertVideo',url,title)}
RichEditor.prototype.insertAudio=function(url,title){this.$textarea.froalaEditor('figures.insertAudio',url,title)}
RichEditor.prototype.onSetContent=function(ev,editor){this.$textarea.trigger('setContent.oc.richeditor',[this])}
RichEditor.prototype.beforeCleanupPaste=function(ev,editor,clipboard_html){return ocSanitize(clipboard_html)}
RichEditor.prototype.onSyncContent=function(ev,editor,html){if(editor.codeBeautifier){html=editor.codeBeautifier.run(html,editor.opts.codeBeautifierOptions)}var container={html:html}
this.$textarea.trigger('syncContent.oc.richeditor',[this,container])
return container.html}
RichEditor.prototype.onFocus=function(){this.$el.addClass('editor-focus')}
RichEditor.prototype.onBlur=function(){this.$el.removeClass('editor-focus')}
RichEditor.prototype.onFigureKeydown=function(ev){this.$textarea.trigger('figureKeydown.oc.richeditor',[ev,this])}
RichEditor.prototype.onKeydown=function(ev,editor,keyEv){this.$textarea.trigger('keydown.oc.richeditor',[keyEv,this])
if(ev.isDefaultPrevented()){return false}}
RichEditor.prototype.onChange=function(ev){this.$textarea.trigger('change')}
RichEditor.prototype.onFormBeforeRequest=function(ev){if(!this.editor){return}if(this.isCodeViewActive()){this.editor.html.set(this.editor.codeView.get())}this.$textarea.val(this.editor.html.get())}
var old=$.fn.richEditor
$.fn.richEditor=function(option){var args=Array.prototype.slice.call(arguments,1),result
this.each(function(){var $this=$(this)
var data=$this.data('oc.richEditor')
var options=$.extend({},RichEditor.DEFAULTS,$this.data(),typeof option=='object'&&option)
if(!data)$this.data('oc.richEditor',(data=new RichEditor(this,options)))
if(typeof option=='string')result=data[option].apply(data,args)
if(typeof result!='undefined')return false})
return result?result:this}
$.fn.richEditor.Constructor=RichEditor
$.fn.richEditor.noConflict=function(){$.fn.richEditor=old
return this}
$(document).render(function(){$('[data-control="richeditor"]').richEditor()})
if($.wn===undefined)$.wn={}
if($.oc===undefined)$.oc=$.wn
$.wn.richEditorButtons=['paragraphFormat','paragraphStyle','quote','bold','italic','align','formatOL','formatUL','insertTable','insertLink','insertImage','insertVideo','insertAudio','insertFile','insertHR','fullscreen','html']}(window.jQuery);