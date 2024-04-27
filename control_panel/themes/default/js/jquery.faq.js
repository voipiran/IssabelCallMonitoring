
(function($) {
  jQuery.fn.faq = function() {
    return this.each(function() {
      var dl = $(this).addClass('faq2');
      var dt = $('dt', dl).css('cursor', 'pointer');
      var dd = $('dd', dl);

      dt.click(function(e) {
        $(this).next().slideToggle();
      });

      $('<a id="faqtop" style="display:none;"></a>').prependTo(dl);

      // Mostrar siempre los elementos
      dd.show();
    });
  };
})(jQuery);

/*

(function($) {

  jQuery.fn.faq = function(tog) {
  	return this.each(function () {
        var dl = $(this).addClass('faq2')
        //var dt = $('dt', dl).css('cursor', 'pointer').addClass('faqClosed').click(function(e){
        var dt = $('dt', dl).css('cursor', '').addClass('faqClosed').click(function(e){
  			$(this).toggleClass('faqClosed').toggleClass('faqOpen');
            var sc = false;

            dt.each(function(){
                if ($(this).hasClass('faqClosed')) sc = true;
            });

            if(!sc) 
                $('.faqShow').text(arrLang_main['LBL_HIDE_ALL']).toggleClass('faqShow').toggleClass('faqHide');
            else 
                $('.faqHide').text(arrLang_main['LBL_SHOW_ALL']).toggleClass('faqShow').toggleClass('faqHide');
            $(this).next().slideToggle();
  		});

  		var dd = $('dd', dl).hide().append('<a href="#faqtop" class="faqToTop"></a>');

        $('<a href="#">'+arrLang_main['LBL_SHOW_ALL']+'</a>').addClass('faqShow').click(function(){
            if ($(this).hasClass('faqShow')) {
                $('.faqShow').text(arrLang_main['LBL_HIDE_ALL']).toggleClass('faqShow').toggleClass('faqHide');
                //dt.filter('[class=faqClosed]').each(function(){
                dt.filter('.faqClosed').each(function(){
                    $(this).toggleClass('faqClosed').toggleClass('faqOpen');
                    $(this).next().slideToggle();
                });
            } else {
                $('.faqHide').text(arrLang_main['LBL_SHOW_ALL']).toggleClass('faqShow').toggleClass('faqHide');
                //dt.filter('[class=faqOpen]').each(function(){
                dt.filter('.faqOpen').each(function(){
                    $(this).toggleClass('faqClosed').toggleClass('faqOpen');
                    $(this).next().slideToggle();
                });
            };
            return false;
        }).prependTo(dl).clone(true);


		$('<a id="faqtop" style="display:none;"></a>').prependTo(dl);

        if(typeof 0 == 'number') $('dt:eq('+0+')').trigger('click');
        if(typeof 1 == 'number') $('dt:eq('+1+')').trigger('click');
        if(typeof 2 == 'number') $('dt:eq('+2+')').trigger('click');
        if(typeof 3 == 'number') $('dt:eq('+3+')').trigger('click');
        if(typeof 4 == 'number') $('dt:eq('+4+')').trigger('click');
        if(typeof 5 == 'number') $('dt:eq('+5+')').trigger('click');
  	});
  };


})(jQuery);

*/
