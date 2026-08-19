/**
 * Select-all checkbox on the SEO focus keyword audit screen.
 */
(function(){
    var t=document.getElementById('sk-seo-check-all');
    if(!t)return;
    t.addEventListener('change',function(){
        var b=document.querySelectorAll('input[name="product_ids[]"]');
        for(var i=0;i<b.length;i++)b[i].checked=t.checked;
    });
})();
