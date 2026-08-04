<?php
/**
 * BT Transfers — Frontend module (ported from the DTF Studio - Frontend snippet)
 *
 * Shortcode [gang_sheet_builder] — outputs HTML, CSS, and JS inline.
 *
 * Only the PHP function name changed (btgsb_render_builder -> btdtf_render_builder)
 * plus the dormancy wrapper at the bottom. The shortcode tag, the nonce name
 * btgsb_nonce, the AJAX actions btgsb_save_sheet / btgsb_add_to_cart, the
 * btgsb_settings and btgsb_product_id options, every DOM id/class and the whole
 * JS payload are byte-for-byte unchanged, so the builder behaves identically
 * and keeps talking to the Backend module exactly as it did to the snippet.
 *
 * Original header:
 * DTF Studio — Frontend
 * Features:
 *   - Auto-nesting (Maximal Rectangles, Best Short Side Fit)
 *   - Rulers (top + left, in inches) outside the canvas
 *   - Click-drag to MOVE individual placements
 *   - Click-drag to RESIZE from corners/edges (corners respect lock)
 *   - Live tooltip during drag showing W×H or X,Y
 *   - "Auto Layout" button to reset back to fresh auto-nest
 *   - Rotation-aware DPI calculation (no longer changes when you rotate)
 *   - Names & Numbers generator: roster -> individual transparent PNG pieces
 */

defined('ABSPATH') || exit;

function btdtf_render_builder() {
    if (!class_exists('WooCommerce'))
        return '<p style="color:red;font-weight:bold;">WooCommerce must be active to use DTF Studio.</p>';

    $s    = get_option('btgsb_settings', [
        'canvas_width'=>22,'max_height'=>300,'start_height'=>12,'moq_sq_in'=>264,
        'margin'=>0.15,'padding'=>0.20,'dpi_good'=>300,'dpi_fine'=>200,'export_dpi'=>200,
        'button_text'=>'Add to Cart','button_color'=>'#27267e','pricing_tiers'=>[]
    ]);
    $btn  = esc_html($s['button_text']  ?? 'Add to Cart');
    $btnc = esc_attr($s['button_color'] ?? '#27267e');
    $w    = esc_html($s['canvas_width'] ?? 22);

    $config = json_encode([
        'ajax_url'   => admin_url('admin-ajax.php'),
        'nonce'      => wp_create_nonce('btgsb_nonce'),
        'settings'   => $s,
        'product_id' => (int)get_option('btgsb_product_id', 0),
        'cart_url'   => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart'),
    ]);

    // Self-hosted font base64 data, broken into ~90-char chunks via PHP
    // string concatenation. At render time these concat into a single
    // continuous base64 string with no whitespace, which is what the
    // browser needs inside url(data:font/woff2;base64,...). The chunks
    // keep individual editor lines short.
    $btgsb_font_athletic =
        'd09GMgABAAAAAAdUAAoAAAAAFSQAAAcIAAEAAAAAAAAAAAAAAAAAAAAAAAAAAAAABmAAXAqgIJdSATYCJAOCFAuB'.
        'DAAEIAWCXgcgG5kPUZQMUpDiR4Hb9GBGok10iIfjQ43+IubbMaTxcL096hcP1SH+u730aplgqHcNNvBWgELpogGP'.
        'Bixgbfzzn9lDvGrS5pQUJ+APcLd9WVk9BB7kZVMoKYwlCyXpNRV5rzr1dg8w/n6t5X97h7Wl0XStUSIxrth7J+YV'.
        'EU3ijVAhFNEm3q9BiqRMCoROazSGXnnZZhfHJouy3z5CDSsaLKDYE5PxCg5TPk2XCkP34tqtk5trOXy11IjcAF1I'.
        'J9GbibWBMdAWQIWBxI4Ara7koV2scSz58uCPL8XgTFC+Hxq5Vh+R3/kgb+etvJxnFVPTnoYHE45BwwdNvlMdzijs'.
        'ZV7KAcLRPM7JYzMnCmUVrlhwyc/iJWNshbE1Fh1dDHhHGYsSxjJ25EWMbQl2tDex05M8JTdnbSxhauWMnW2NDZw8'.
        'zfRpInIH2bLOQsItCKKgkA4CEml2Z6paaKIOK80RTEVBpTxBGgWIchivUUSAININnCIARRRRoIggZhRqauuI2gaJ'.
        'm0CIqB1QeSFBSpMENTFGgOZ2tYI2W9miwo2JuobrnIBoE41ihk19iPOaqjyn6FSbaygSoGIpxoRHzJSdv18LolIg'.
        'zSgQRL2JJ9XUuGzdGJGS+trdHSgY92C6t1mlFojDjskSwhyywwArL6KezcRZSrXEEEGBYZOw7Zo+RVYBTu4qsE3n'.
        'smAoQahYDCtdNn6lETmq5gBwqn0LaBrqkuRxiJQSZJVvSBpzKYUCzVptY7QR6hTi2sbcbWqd/HQDiI62IECp1ugQ'.
        'tUV9IF6L16sVY3GVgIRx5cZzyc+oSMmJU75ML1EzL4VCA72GAGlKmiNcoyYIEhGtkVKw8fP5mzTGvDotVi+EkKSC'.
        'drjC6bt6iILWBYHmm7YjRff/14sPORxid7jFMUgZwVsxvVLaHgeNVFo/DQ7NXjENTXJlCoLAsMjRiEhycg26Hjxd'.
        'YSDzeGqbhorcE5e4dHj1dmlm+sf7w5Wm+bFkCEm4RMWg189WGuDxbHLz1U2TqxVrg2b6IonYwKBXfw4mEEQgLoXb'.
        'v+JOaIMGLKnsXMJkg8Wzl7IPnBt7Cmryo3O7nt9dHrJ8/a/6WjwsJCo2dngiducWeFFfT8pIYlgekYSRM/PS1o1B'.
        'eZXS9eL2i1Ais8SFf7opiz7NjCheRvMDqgCf5SIIEQQ/LC8JJcIfphfwQlymQtYq1sLi9WfWw/KGGKmYl7p+v0p5'.
        'FVf5DzSBb1m6BW5en2LXpBMAI58NHrN1vNhV5rK6erebaMq+zaENbsBVJgDdTPOVmuKfS7tK45WS+Zvmq5iWHlsK'.
        'te7zLpWUpGKYMlCWFx42Z27sdmQnnIQDMCVDN8N0oGFl34rQSWQmSBJL1c00GL+clzRnV/NQ86GFpQqwtx/kIw1M'.
        'hi/nJi3Y1TbUdigdcIVU6+vPkfNTeqir5RUm4eHwqOpoKjNWmZqqm2YwovhbhrcIKXAiL/xQA2Y8KDoErlOBQ7hH'.
        'ZNUS6mrI7XPbm9B0kyHFDyFJSJhJqZrC1zxZ7eo/qMYiE1cV1PEHjWEgQ/B43qLWhXB/6lSp4gdhnSX9/cHhWYjb'.
        'KE07Vv9PH+7Vtq2kZZsXc8h4SKCdYfaodAQEbMwZOlnsVd+G51Mnmw7ydVLNt8YpdeNAniNT4AT+ht4yZnI1ug6i'.
        'wVzP004zLySiaGTsfjiZFqnTeUsJVFRgQziRHBmxeMaDpDxI5tReNCe1lmubCHZ2Z/SLMO7Ekn3jSEbZQW/2iDnl'.
        '7tban0Boj9rZDR8X5Ow4bFNeGhoRmhRDKhelb92GlJZG4lhUQjwwkSWhCa163pIPo7OZaANo5j7otGDf/pvMDZlM'.
        'QthN5h0Ivg6elH/fFNU37Try3OguoMSjnsOQbMieoo98N4W6tUaLi5IE3k0s6r0pIGNyOKEU3r2b6tujMwj8jQ+N'.
        'zoyagGoX6q7Rt+bfA4lkz5m7dLa4xwVym11MYJos7sLwVOeRR0YXRjutzZX8jxvw6iZMdkcntR/9fovSt+k7UPEB'.
        'Z/5n1KYAdO37fqCFQcYPbapC7QW83z3dBH++l4/k/9xYXYpUxclqPxFzk9LAaub/gmCvhmAIlJVVgy9eeTPh/51i'.
        'mFXZwYcdiAoQ3f+TI4QYBeUf6YDRuK2HakSDZTx8n02w8n8wEFhIWs9YlbBhYUYl4llyabUqg4OYDJ41wSL6M8MT'.
        'WID5uMs4NNi0a9w0Ji2Bk8SXMLWmXFXHHi9qGxRTtFljihA16ACYVxEGscVhOJIihldHYTBfyxgLWY7ZLTVE6fxX'.
        'xBH4BTAhhOAgT0/CtdNKGx35dtAX0F2127+TNjHU9dUO+ukqB70p0LWs9jFIHw3VUD10R6EHDXqn0FNeLKA3/2hM'.
        '/tgcrazkq3dm0uLarTIEYK6SfKyrZ+Qp5+kxAA==';

    $btgsb_font_college =
        'd09GMgABAAAAAAs8AAsAAAAAGwQAAAruAAEAAAAAAAAAAAAAAAAAAAAAAAAAAAAABk4ATAqqdJ8xATYCJAOCIBOC'.
        'MguBEgAEIAWBMgcgG+gTUZRNVgXiR0J20nh3RCKRWKtjNfOhRyMkmfXhadN6fCR41Camm9ShFoWaDGygHvlJaBkq'.
        'RmpCxYR296aiyZpmTxLOqAmpmAwF8O8/m1ZAPjCUluy4q0pU4VOuq2TW9mTgXdPy5pvztZfeIJlqX+eqhiTclJ1x'.
        'd5crXK7wUs7PJ4Jk5DvFoP53QKQ2NnpfDgjKyXCnq1YGzJD/pGOgjYEt/DWLeYLSvIAyTLQGUkDcnrQB7ElqoFWS'.
        'er+ETMR3p0Vg0KBhP28CNCyxARBBDBEANSQook3KQSABgwzoYEWDPwAG6chDMcb4+aa3Xn+pt4cIP7wXjb0GwDBA'.
        'WgsovjBCdYA4B0S/jIQViE0MEhAfOh2QdG7u9bAMiSRNcg8ql6uC4wPFEcFyhVaiZNIwsUYmC1PKoiIi5nvh84uY'.
        'osJPGJVzZ/Ui3wxvX5yj04oSUGe0imfKfPYjmwId3u3Ci3jyLHO/62XWR+CFIeoEDVWyLhzligPP9antWAvqFAu8'.
        'PFINqumAAQWgmsiy85BCWDovU2jjpJZmdYVNlgL00nVLpik1kBeurQnTsKFrsoijnpzzDlCbynNoFEfGkSowyl0z'.
        'IsuDA5skTWYKX0cXX2OT0O8mip3JprQ8LGkkstWppt2cR7Y5rKzhJ9STSLIufzd7CgsSQURyV3ZKVygpDmwSwatn'.
        'DhIvaN8HKvNIjqdT5BaWgimGWpsbPcNLyA6XfxFZkTpWTpfDQjX4Byr2uy5ics8BuybEKbdH3V/rxLddRtTccHO0'.
        'qUvOUDHAM3cayOTXE3e+6Yacof58atshu922Pc3jLW0/3iVFG+Bsp3oze5mWd117mZjKs9r7nA6RH0KwW4wPWhmB'.
        'poIXJcqOoebNJsh0mOtJx+o6lXqZt1XyYlHM2kw31CsbjdOCVKZeujmOLG0Z8NW+Nnlgo/1if7w40dpfcDtsoa1G'.
        'uCgn8EQIBRS5F0B1VxGoN0VwQgoQOuM9qV0UKfmGlvEN6foGCx1H1nyRiWiNiKMjwz49uz8kkzXwRD0wfnL3FIQi'.
        'a8zS5JcTkxyVmqu7ZdwZpmBThNsQYG33US+3uB8e4gg5ytrCYxddOyZrU2tfj5soqSyCq5j9AxaU4dcKvc1shuzm'.
        '3J5JP7GyaeHY24IPqyRP5qVVMDUz2VDSMjbPYiXGLP4x5pJPn5iZefy3IvbPwyUxjI5/bONectx2jjtw/AA7+Esb'.
        '5+afMan2i+Ti+Lcau+7OiGWY8c4SkBy9NKklCZDmELYIBHVUAGwelr/qA6r2DDPiGUYKFCGCXjvtrzm+LmQNnO2l'.
        'zYaYr3/k4xsaLkI5pILYfgXhYRZP0U/Zugexo6RPr3JcHcdV8G6GMc0FwyyCIbPjDNmMQCn3+Y7Q8Ver1wXfO1l/'.
        'yX+Fj/veEY+Nc5OEHz1lg0bje1NzhRYyc82PNWWecprm9wP6v63l6uUiIpXF2yv5CND/337IMJAq9wVBqB0P6L+G'.
        'mWF+rupgHt+mS9n0E0t33hd26ZuLlM3nuzeY2zykZVcBsMV8+MKFk6nMiAIkMifPXDhz5mTiGaROPmKiuZ4bWG9b'.
        'Z7cNtHPRaTnD2reeQwliGowzsW4m9XuGqVyD1KlZqsCUy+Kvcrbu8aTsv+5zXDLvflsk9l9lhcP5d1kPGfwMxFG4'.
        'fWmH7Q4Jjm/Nk3+eXS9kG3NOXojDwXT53URmIQPiQQDrEQRyFlPna/vHE7vdS712G2QNds77jDdz2BfVkT+5c7fe'.
        'yt9eiixAwwJhtz14mnEaCzgOjJOI2UWW7ng2Mxb/+SLiMuctdwlqwbUcg9JylmctJaPh8/bYF1kHqXH2xA/80QxH'.
        'KiXfYkIK0W3XeXqTomFxkuDxbve2ggJZzQgqQa0zRzIGwTq9GfKODNMWT+rKP+VszxtDdj9FsOyBUEzn8CffvHPD'.
        'KtSMbhir8+7qWtVEiB6CjOxj3L0fxgbz10OXJfxn57PTAJgq2pe/4ewUvFZ9CmsyCqdk9EmT2Aaagrm/Qmd698b3'.
        'cbP+YHRs1jeB/Ns9vjBN26KGovvfQDLy+FWvnRv3qDog7XJAdW11QMCq1c+f4z2ZWyAOWLIg7ftpUJ9P5iHTPtjt'.
        'XsH7UkLA00c3pzwcvqc3fQ/PAqZXcPa7hu+6QV/Nl8K/3ojTvZcKnoUgAhqtWk4XAUdvJGUZlXdchoqy+3bbK5st'.
        '5Z59/sCtmK03c0LFIAjtMzqzx8mE0SDr09+yIYIZECZG/jkJm4cSjtiDQBwm0RI0hStyA41kqUMYVQRhrEAW1uKX'.
        'g5i2zesIGCHoZXtMXbhjy1bXTmf01L/kI+/ZAx5XAd3acqFyUUagfj8f1UCiq5PlWN8kIZ33jFZSnjokYir0G0oK'.
        'PeQ/u+fCNbTzf5SlZclYEHOYLk874HPTeDoyc/bZ3NlWGkbd5hKn0aSNP48+dgQLb51NPNFY/yuoG9JeLJAz1U+n'.
        'TulWmCzOR06TFdIcwKryFsw7t/6FJdVWWiz+5BbrSpPV+GJIU+PxxkYEFDUCmnQoD4Ngrz1wmi7cBzYOdCHU56Bn'.
        'Z42fFYv/8yx1+H5xOGgRHetAkSTJ8UM36vF4EvPOPtOPxJEv2KxG/uxpB2slbQlvcdOOKDJrdYY7NR3db7H9xNDR'.
        'TGkQTJEbIFvT2HgrVtrwZKnF+mvOhYtu0xn01BFupkWLFhroYAa07dbt9+9bpYZ9ZLL5s6GLMnzqPnJT5ewCxPSD'.
        'kYWD0lIuN+M0sWdUp2WcTpugkVPrrxm1BchPbR7lV8eZf6Ztxv6RCcjfFE2KlxchcUhTJ6qD+j/VqoMQdDcbdOn7'.
        '30anyZRytcPYJem9+Pg9e7mpf/6J9MRPP+3YibLdfYHGYMccbrruncnipE7YpbQJ+rPxiY0nMqD1+BehYOmhFlOC'.
        'wSPz1DVPCHA+wZqMfRNPcCXUibJoNFGuYYAVwU3eKZCtmTW4yBqFU7prH6wm02CllL5JeO1bTq0+0GmJ5b6LtA81'.
        'h7xcBWcIHUa/XANqNVSYRMeGnFoU8820mVp7iFTfSivp1USpz9wKsETzxdUY9Qff1zjJcmgBKsn7x+/vkSoGq5rU'.
        '1zDQMKnqQ9VFExVGuFj5oXISpkr+V5tZUW3n0ClkuyfjjMYSo43epxfR7QX9vTiScs5cyEs29fjDTSUzvl7R7TPm'.
        '+d4NHRXY7bVcHAQAmJm281cAuJPr/RMAwmUaFoAMJBBZ6uf5pZP+APzPzlDU7hDppfZ2kShx71AVCZCrgMwASCig'.
        '3AKocoCAOkC52gWlNg+SqMh20JSFQVUNaOUkh0gqO2hWlS5Ae1E9R5XpALfDGmWoGti0GexDVDkldZIQyQA4HtY0'.
        'FREOrPgjKp+cohoOKHoKSlAmyT1s/QZCRGWhKM2R5PfZ2RTsCal/C8UtGJ8z3HdTBb5XlNOCWCl4JVitqunuf7KQ'.
        'qig/WVoBfoUUo6GC1h9ChWBBgFpav6eW/M6Gof5ttFD5eaD61GA/KxjaQ2HI+PzOlukfzrOIZxPMtRULFJbCMsGg'.
        'RSOsASHk2xORAbNgtjX3cwhqdnzehhQEi18IM0AGliAzyz9clhJm8WziucTzp4b5MB/m8/bOXOcAdVAHdI7PS9Gd'.
        'FzOwFJZBgyJqLQfSIIYiM0iTg+6niiQKADFUvFx0jECJGKPYZJTQMSm0SDXKiAVQsR1K4cC4oNWg9tTyULI9PgZV'.
        'GCuwziWtPQoTpkuHZlGOYvQHAyOGiJaD0Au9UYqhxVwB/znZLgI=';

    $btgsb_font_varsity =
        'd09GMgABAAAAAAcMAAoAAAAAFSwAAAa+AAEAAAAAAAAAAAAAAAAAAAAAAAAAAAAABmAAVAqaTJILATYCJAOCHAuB'.
        'EAAEIAWIOAcgG6wPUZQtUpjg52EMbtMa0xZ1UX7UqVyJ8z/gWmRlj/LhaZvv350R/a0sYm2cIOgiA7k1LFh3Mxdp'.
        '5bDdpfzDXkLR3ZbuGYQpLcn3Egk9CqNwCmXhedu9bQAURhRw57GE8juq6GHz0VqrO4glLpNssUTIeOmUcoLNzds+'.
        'Yt7ELEEkRLqJJO1kcvrc6ZXQeOg0Q0GjO6ENxTW/ngEsOUamwgBg9jEqAOp6LuQ+LvELdWSCC1VtkRgjQTF46P8k'.
        'YClOcQWZSqodADIILNojEZQ4ZF3ZHMZgwIEiEMnoYHACoAhAOLLxaf3o0zzI7VzJOYyWW6H1JwPoAGwSwO1l2JsT'.
        '43GAgDAmDoGRWDAG1nTVcWSCIb0uLTUwcDLk3NLGRrZOrJktZ2PCcZbedqwxx5lYc4bmpnbmnKkZCs64w8zm0dF4'.
        'C6reAcx4satW3LNYmB4JAXYAQ9eSFhYSKmHB4l6mfKLUok0jE1yyNV8qsOD74qMKIE9O14RHaZRViBSQUC21j1U1'.
        'yUFm8UotUTUltLv25EiiCq5FwpqBeUjMv4pINq2NXoOjutyScEyZC1hLuubjzEDjqSKi6ZeYNesTJASUzA10Qmnd'.
        '3Krgqj4WHwkMIyy0Hcwha8/ExJ6DYogooQCUJQg00O5FJNR1VJ4o0VRWtZg9V9hWA82I0paMyPm6+cUaUipiTWtR'.
        'Exr6MPW8SBm0Sdcd6aQG4QKXSHaP6RM0S/Wo++Uj6ic7T88wt7GVL53gUlc6sVs+8e1jLxHNei4arfa/2T4FtJoS'.
        'a1JvlLqCbkqDW8VTW8hxDyeXer0/wLn//1r6/F5/NqS39Lf7LZ/0T0ZbVyVXT5ujg/5BfqPqy/5v6laaqQ3FgDN0'.
        'APy2UterdAH+zjimVQNqeAR0KRfZVYi6VPB2cNRTKihSps2GyVVqUMy7day+tTdOe1ihHrFs07IMQYHnDy0TsxPz'.
        'fkp9VxojlOoOayZojunihRol/x0p4U2vm3fOCN+s2ry3Y//v31ffwwmvEDKWPliqlkn2aiOEW9Kqkjyew/6yPbo9'.
        'TjG6rRfaZ1e2r9Yr/Z75s6YftfUHc5XO9uNf7F/7XDMiYTra3cO6w+bHdMcmH11CqoSVi/8ZkIOplpJMOCgT7f4e'.
        '3Z7fFb94oX1JyJisVs6vNR1JWTFsAFuUNT9w+Qrv5TrTWHoAwZopJ6b07d5pdtqIYT7dRFJaJ5OB01O7cr55SPZy'.
        'LM8eMvZwFEnd+Zg/PbCLkr21FGrNvp0Td+57rO1Ou6vQQpbvmrhLpIYN4yTZG7BhU9ealzX6c1WHTYO7rCmoWFch'.
        'eGkwvhp+Ewq3XHpOLn7XIB42PVWjwq8l+iULj6SkClQIPBKwEE1P20wFFXxiKRWWjr8z6s7SmG1GtY5vhZue0slL'.
        'x40XYNdJKkzVCoAAz4AuFSLb2b+TLahGo9fMcJX1Kdhur6bZMN//eCwem2cWldfPXqQoLs8v7sgUdQQ9eUF7IfPk'.
        '3F4ZcZkttErOu8lliCpPXrb9UE1JXI3oSWfRkLHZc7eekja4deaf1PA9sVzTop2sFVQr4OZLcS5j9lfEuemYIAAT'.
        '4TOGWtjRaeYyOr+/4WBDhMuoKZ06NTmjg1MDW8wvFHKFsTlCkYDKdVIHWbtq5mLV8qrD8cudTNWH91F68meAOqDO'.
        'm90vDvCHIRlDNT3fuDzXrhR/jLx1pR1bBpJ/wSuruta6pbUab7GvQMCZ56usAeBF2LUWAGBusBSACRgAAAH+3kqd'.
        'APizZgDDBiqsr8iE4ZcVpLMPp8GUXRFMHy05IRZWQShEg58xgoJw+8iGcoCxBcgHHyzYBleyhP1xYdxd8RSNNQt3'.
        'JXNhENprBhfyWZDnGpzhp/GJhMldWSdIZ+LZyW9gvvDaFU7/rCAn5cEJ7BQErf0ssAQsCGcCYDRAcQKK0TkMLLmc'.
        'RY5NnEMUvnAD+BAfbogwkseN0Lt6cmOEkCfcBFVtzk3xlWlzMxRMFZnr183VQ0+D9NZMqUlfFEGQCQZFDKIQjWhQ'.
        'NBiEIk9Dc1NeVxTZesvSxHrQ2M9iM1R010cEi7qy281AfUhs9KER+wPKBZBH4tGwJ6ojUx9EGaoiEO2m1E9XvN6i'.
        'EYGoznqpqNKeiFjqWO2hPQwSrns272AUqc2qjX32aKYriTsnW1oFNX/x5FH0lS3KgbqNkdYFZY+i4kZ6S4ISk6ag'.
        'QOp+71jE8mzWRyMpVzZqkWvo1RPveAakGGu0+75BCCP5a/NqptQ9bjSb2qm68v7dXwRFvHfsq9NSENmxHzA9AvKR'.
        'iYfB+wBFnBB2kyewaEX1f7sYAAAAAA==';

    ob_start(); ?>
<!-- DTF Studio - Gang Sheet Builder -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Passion+One:wght@900&display=block" rel="stylesheet">
<style>
@font-face {
  font-family: 'DTFS_Athletic';
  src: url(data:font/woff2;base64,<?php echo $btgsb_font_athletic; ?>) format('woff2');
  font-display: block;
}
@font-face {
  font-family: 'DTFS_College';
  src: url(data:font/woff2;base64,<?php echo $btgsb_font_college; ?>) format('woff2');
  font-display: block;
}
@font-face {
  font-family: 'DTFS_Varsity';
  src: url(data:font/woff2;base64,<?php echo $btgsb_font_varsity; ?>) format('woff2');
  font-display: block;
}
</style>

<!-- Font warmer: forces the browser to load each @font-face immediately on page
     load instead of waiting until first use. The element is hidden, but its
     contents must be non-empty for the font load to actually trigger. -->
<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;font-size:10px;opacity:0;pointer-events:none">
  <span style="font-family:'DTFS_Varsity',serif">Aa</span>
  <span style="font-family:'DTFS_Athletic',sans-serif">Aa</span>
  <span style="font-family:'DTFS_College',serif">Aa</span>
  <span style="font-family:'Passion One',sans-serif;font-weight:900">Aa</span>
</div>

<style>
#btgsb-app {
    display:flex;
    gap:24px;
    align-items:flex-start;
    max-width:1280px;
    margin:0 auto;
    padding:20px 0 40px;
    font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
    font-size:14px;
    color:#1a1a2e;
    box-sizing:border-box;
}
#btgsb-left{flex:0 0 360px;min-width:0;display:flex;flex-direction:column;gap:14px}
#btgsb-right{flex:1;min-width:0;display:flex;flex-direction:column;gap:16px;position:sticky;top:20px}
#btgsb-upload-zone{border:2px dashed #9b9ad4;border-radius:12px;padding:32px 20px;text-align:center;cursor:pointer;background:#f8f9ff;transition:border-color .15s,background .15s;user-select:none}
#btgsb-upload-zone:hover,#btgsb-upload-zone:focus,#btgsb-upload-zone.btgsb-drag-over{border-color:#27267e;background:#f0eff8;outline:none}
#btgsb-upload-zone .btgsb-upload-icon{color:#27267e;margin-bottom:10px}
#btgsb-upload-zone p{margin:4px 0 0;color:#444;line-height:1.5}
#btgsb-upload-zone input[type="file"]{display:none}
#btgsb-namesnum-panel{border:1px solid #dde2ef;border-radius:12px;background:#fff;overflow:hidden}
#btgsb-namesnum-header {
    display:flex;
    align-items:center;
    gap:8px;
    padding:11px 14px;
    background:#f8f9ff;
    cursor:pointer;
    user-select:none;
    border-bottom:1px solid #edf0fa;
    font-weight:700;
    font-size:13px;
    color:#27267e;
}
#btgsb-namesnum-header .nn-icon{font-size:16px;line-height:1}
#btgsb-namesnum-header .nn-caret{margin-left:auto;font-size:11px;transition:transform .15s}
#btgsb-namesnum-panel.nn-open #btgsb-namesnum-header .nn-caret{transform:rotate(90deg)}
#btgsb-namesnum-body{display:none;padding:13px 14px 14px;flex-direction:column;gap:10px}
#btgsb-namesnum-panel.nn-open #btgsb-namesnum-body{display:flex}
#btgsb-namesnum-body label.nn-label{font-size:12px;font-weight:700;color:#555;margin-bottom:3px;display:block;letter-spacing:.02em}
#btgsb-roster-grid{display:flex !important;flex-direction:column !important;gap:6px !important}
.nn-row-item{display:flex !important;flex-direction:row !important;align-items:center !important;gap:6px !important}
.nn-row-name,.nn-row-number {
    box-sizing:border-box !important;
    border:1px solid #c0c9e8 !important;
    border-radius:6px !important;
    padding:7px 9px !important;
    font-size:13px !important;
    font-family:inherit !important;
    color:#111 !important;
    background:#fff !important;
    height:auto !important;
    line-height:normal !important;
}
.nn-row-name:focus,.nn-row-number:focus{outline:none !important;border-color:#27267e !important;box-shadow:0 0 0 2px rgba(39,38,126,.12) !important}
.nn-row-name{flex:1 1 auto !important;min-width:0 !important;order:1 !important}
.nn-row-number{flex:0 0 64px !important;width:64px !important;min-width:64px !important;max-width:64px !important;text-align:center !important;font-weight:700 !important;order:2 !important}
.nn-row-delete {
    flex:0 0 26px !important;
    width:26px !important;
    height:26px !important;
    min-width:26px !important;
    max-width:26px !important;
    border:none !important;
    background:none !important;
    color:#c0392b !important;
    font-size:16px !important;
    font-weight:700 !important;
    cursor:pointer !important;
    border-radius:4px !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    padding:0 !important;
    line-height:1 !important;
    box-shadow:none !important;
    text-transform:none !important;
    order:3 !important;
    transition:background .1s,color .1s;
}
.nn-row-delete:hover{background:#c0392b !important;color:#fff !important}
.nn-row-delete:disabled{opacity:.3 !important;cursor:not-allowed !important}
.nn-row-delete:disabled:hover{background:none !important;color:#c0392b !important}
.nn-add-btn {
    margin-top:6px !important;
    padding:4px 10px !important;
    border:1px dashed #c0c9e8 !important;
    border-radius:5px !important;
    background:#f8f9ff !important;
    color:#27267e !important;
    font-size:11px !important;
    font-weight:700 !important;
    cursor:pointer !important;
    font-family:inherit !important;
    display:inline-block !important;
    width:auto !important;
    height:auto !important;
    min-width:0 !important;
    max-width:none !important;
    line-height:1.3 !important;
    box-shadow:none !important;
    text-transform:none !important;
    letter-spacing:normal !important;
}
.nn-add-btn:hover{border-color:#27267e !important;background:#f0eff8 !important}
.nn-font-grid{display:grid !important;grid-template-columns:repeat(5,1fr) !important;gap:4px !important;width:100% !important}
.nn-font-btn {
    padding:5px 2px !important;
    border:1px solid #dde2ef !important;
    border-radius:5px !important;
    background:#fff !important;
    cursor:pointer !important;
    font-size:11px !important;
    color:#111 !important;
    font-family:inherit !important;
    text-align:center !important;
    line-height:1.1 !important;
    width:auto !important;
    height:auto !important;
    min-width:0 !important;
    max-width:none !important;
    box-shadow:none !important;
    text-transform:none !important;
    letter-spacing:normal !important;
    font-weight:400 !important;
}
.nn-font-btn:hover{border-color:#27267e !important}
.nn-font-btn.nn-active{border-color:#27267e !important;background:#f0eff8 !important;color:#27267e !important;box-shadow:inset 0 0 0 1px #27267e !important}
.nn-font-btn .nn-font-sample{display:block !important;font-size:13px !important;margin-bottom:1px !important;line-height:1 !important}
.nn-font-btn .nn-font-name {
    display:block !important;
    font-size:9px !important;
    font-weight:700 !important;
    letter-spacing:.03em !important;
    text-transform:uppercase !important;
    color:inherit !important;
    line-height:1.2 !important;
}
.nn-row{display:flex !important;gap:10px !important;align-items:flex-end !important;width:100% !important}
.nn-row > div{flex:1 1 auto !important;min-width:0 !important}
.nn-row input[type="number"],.nn-row input[type="color"] {
    width:100% !important;
    box-sizing:border-box !important;
    border:1px solid #c0c9e8 !important;
    border-radius:6px !important;
    padding:7px 8px !important;
    font-size:13px !important;
    font-family:inherit !important;
    color:#111 !important;
}
.nn-row input[type="color"]{padding:3px !important;height:34px !important;cursor:pointer !important}
#btgsb-namesnum-generate {
    padding:10px 14px !important;
    background:#27267e !important;
    color:#fff !important;
    border:none !important;
    border-radius:6px !important;
    font-size:13px !important;
    font-weight:700 !important;
    cursor:pointer !important;
    font-family:inherit !important;
    width:auto !important;
    height:auto !important;
    line-height:1.2 !important;
    box-shadow:none !important;
    text-transform:none !important;
    letter-spacing:normal !important;
    display:inline-block !important;
}
#btgsb-namesnum-generate:hover{filter:brightness(1.12)}
#btgsb-namesnum-generate:disabled{opacity:.5 !important;cursor:not-allowed !important}
#btgsb-namesnum-status{font-size:11px;color:#888;text-align:center;margin:0;min-height:14px}
#btgsb-empty-hint{color:#aaa;font-size:13px;line-height:1.6;text-align:center;padding:8px 4px}
#btgsb-batch-list{display:flex !important;flex-direction:column !important;gap:14px !important}
#btgsb-batch-list:empty{display:none !important}
.bb-card button {
    box-sizing:border-box !important;
    -webkit-appearance:none !important;
    -moz-appearance:none !important;
    appearance:none !important;
    background:#fff !important;
    background-image:none !important;
    border:1px solid #dde2ef !important;
    border-radius:6px !important;
    color:#111 !important;
    font-family:inherit !important;
    font-size:13px !important;
    font-weight:400 !important;
    text-transform:none !important;
    letter-spacing:normal !important;
    line-height:1.2 !important;
    padding:6px 10px !important;
    margin:0 !important;
    height:auto !important;
    min-height:0 !important;
    width:auto !important;
    min-width:0 !important;
    max-width:none !important;
    cursor:pointer !important;
    box-shadow:none !important;
    text-shadow:none !important;
    text-decoration:none !important;
    transition:none !important;
    outline:none !important;
}
.bb-card button:hover{filter:none !important}
.bb-card input {
    box-sizing:border-box !important;
    -webkit-appearance:none !important;
    -moz-appearance:none !important;
    appearance:none !important;
    background:#fff !important;
    border:1px solid #c0c9e8 !important;
    border-radius:6px !important;
    color:#111 !important;
    font-family:inherit !important;
    font-size:13px !important;
    font-weight:400 !important;
    line-height:normal !important;
    padding:7px 9px !important;
    margin:0 !important;
    height:auto !important;
    min-height:0 !important;
    text-transform:none !important;
    letter-spacing:normal !important;
    box-shadow:none !important;
    text-shadow:none !important;
    outline:none !important;
}
.bb-card input[type="color"]{padding:2px !important;height:32px !important;cursor:pointer !important}
.bb-card{border:1px solid #dde2ef !important;border-radius:12px !important;background:#fff !important;overflow:hidden !important;box-shadow:0 1px 4px rgba(0,0,0,.05) !important}
.bb-card-head{display:flex !important;align-items:center !important;gap:8px !important;padding:11px 14px !important;background:#f8f9ff !important;cursor:pointer !important;user-select:none !important;border-bottom:1px solid #edf0fa !important}
.bb-card-title {
    font-weight:700 !important;
    font-size:13px !important;
    color:#27267e !important;
    background:none !important;
    border:none !important;
    padding:0 !important;
    flex:1 1 auto !important;
    min-width:0 !important;
    font-family:inherit !important;
    outline:none !important;
    text-overflow:ellipsis !important;
    white-space:nowrap !important;
    overflow:hidden !important;
    height:auto !important;
    line-height:normal !important;
    box-shadow:none !important;
    border-radius:0 !important;
}
.bb-card-title:focus{background:#fff !important;border-radius:4px !important;padding:2px 5px !important;box-shadow:0 0 0 1px #27267e !important}
.bb-card-count {
    font-size:11px !important;
    color:#888 !important;
    background:#fff !important;
    border:1px solid #dde2ef !important;
    padding:2px 7px !important;
    border-radius:10px !important;
    font-weight:600 !important;
    flex-shrink:0 !important;
    line-height:1.4 !important;
    font-family:inherit !important;
}
.bb-card-caret{font-size:11px !important;color:#27267e !important;transition:transform .15s !important;flex-shrink:0 !important;margin-left:auto !important;line-height:1 !important}
.bb-card.bb-open .bb-card-caret{transform:rotate(90deg) !important}
.bb-card .bb-card-x,
.bb-card .bb-item-x {
    background:none !important;
    background-color:transparent !important;
    border:none !important;
    color:#c0392b !important;
    font-weight:700 !important;
    cursor:pointer !important;
    padding:0 !important;
    border-radius:4px !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    flex-shrink:0 !important;
    box-shadow:none !important;
}
.bb-card .bb-card-x{font-size:15px !important;width:24px !important;height:24px !important}
.bb-card .bb-item-x{font-size:13px !important;width:22px !important;height:22px !important}
.bb-card .bb-card-x:hover,
.bb-card .bb-item-x:hover{background:#c0392b !important;color:#fff !important}
.bb-card-body{display:none !important;padding:13px 14px !important;flex-direction:column !important;gap:10px !important}
.bb-card.bb-open .bb-card-body{display:flex !important}
.bb-card-body .nn-label{font-size:12px !important;font-weight:700 !important;color:#555 !important;margin:0 0 3px !important;display:block !important;letter-spacing:.02em !important;font-family:inherit !important}
.bb-items{display:flex !important;flex-direction:column !important;gap:6px !important;max-height:340px !important;overflow-y:auto !important;padding-right:2px !important}
.bb-item{display:flex !important;align-items:center !important;gap:6px !important;background:transparent !important;border:none !important;padding:0 !important;margin:0 !important}
.bb-item-kind {
    flex:0 0 38px !important;
    width:38px !important;
    font-size:10px !important;
    font-weight:700 !important;
    color:#999 !important;
    text-transform:uppercase !important;
    letter-spacing:.04em !important;
    text-align:center !important;
    background:#f4f5fb !important;
    border:1px solid #dde2ef !important;
    border-radius:5px !important;
    padding:6px 0 !important;
    line-height:1.1 !important;
    font-family:inherit !important;
}
.bb-item-kind.k-number{color:#27267e !important;background:#f0eff8 !important;border-color:#c0c9e8 !important}
.bb-item-text{flex:1 1 auto !important;min-width:0 !important}
.bb-item-text:focus{border-color:#27267e !important;box-shadow:0 0 0 2px rgba(39,38,126,.12) !important}
.bb-card .bb-item-fontbtn {
    flex:0 0 auto !important;
    background:#fff !important;
    border:1px solid #dde2ef !important;
    border-radius:5px !important;
    font-size:10px !important;
    font-weight:700 !important;
    color:#27267e !important;
    padding:4px 7px !important;
    cursor:pointer !important;
    letter-spacing:.04em !important;
    text-transform:uppercase !important;
    line-height:1.2 !important;
    font-family:inherit !important;
    width:auto !important;
    min-width:34px !important;
    height:auto !important;
    box-shadow:none !important;
}
.bb-card .bb-item-fontbtn:hover{background:#f0eff8 !important;border-color:#27267e !important}
.bb-card .bb-item-fontbtn.bb-has-override{background:#f0eff8 !important;border-color:#27267e !important}
.bb-add-row{display:flex !important;align-items:center !important;gap:6px !important;margin-top:2px !important}
.bb-add-row .bb-add-name{flex:1 1 auto !important;min-width:0 !important}
.bb-add-row .bb-add-num{flex:0 0 64px !important;width:64px !important;text-align:center !important;font-weight:700 !important}
.bb-card .bb-add-btn {
    flex:0 0 auto !important;
    padding:5px 10px !important;
    border:1px dashed #c0c9e8 !important;
    border-radius:5px !important;
    background:#f8f9ff !important;
    color:#27267e !important;
    font-size:11px !important;
    font-weight:700 !important;
    cursor:pointer !important;
    font-family:inherit !important;
    line-height:1.3 !important;
    width:auto !important;
    height:auto !important;
    box-shadow:none !important;
    text-transform:none !important;
    letter-spacing:normal !important;
}
.bb-card .bb-add-btn:hover{border-color:#27267e !important;background:#f0eff8 !important}
.bb-popover {
    position:absolute !important;
    z-index:200 !important;
    background:#fff !important;
    border:1px solid #c0c9e8 !important;
    border-radius:8px !important;
    box-shadow:0 4px 16px rgba(0,0,0,.18) !important;
    padding:8px !important;
    display:grid !important;
    grid-template-columns:repeat(5,1fr) !important;
    gap:4px !important;
    min-width:280px !important;
}
.bb-popover .bb-pop-clear {
    grid-column:1 / -1 !important;
    padding:6px 8px !important;
    font-size:11px !important;
    font-weight:700 !important;
    text-transform:uppercase !important;
    letter-spacing:.04em !important;
    color:#888 !important;
    background:none !important;
    border:1px solid #eaecf3 !important;
    border-radius:5px !important;
    cursor:pointer !important;
    font-family:inherit !important;
    margin-top:2px !important;
}
.bb-popover .bb-pop-clear:hover{color:#c0392b !important;border-color:#c0392b !important}
.bb-card .nn-font-btn {
    padding:5px 2px !important;
    border:1px solid #dde2ef !important;
    border-radius:5px !important;
    background:#fff !important;
    cursor:pointer !important;
    font-size:11px !important;
    color:#111 !important;
    text-align:center !important;
    line-height:1.1 !important;
    width:auto !important;
    height:auto !important;
    min-width:0 !important;
    max-width:none !important;
    box-shadow:none !important;
    text-transform:none !important;
    letter-spacing:normal !important;
    font-weight:400 !important;
}
.bb-card .nn-font-btn:hover{border-color:#27267e !important}
.bb-card .nn-font-btn.nn-active{border-color:#27267e !important;background:#f0eff8 !important;color:#27267e !important;box-shadow:inset 0 0 0 1px #27267e !important}
.bb-card .nn-font-btn .nn-font-sample{display:block !important;font-size:13px !important;line-height:1 !important;margin-bottom:1px !important;font-weight:400 !important}
.bb-card .nn-font-btn .nn-font-name {
    display:block !important;
    font-size:9px !important;
    font-weight:700 !important;
    letter-spacing:.03em !important;
    text-transform:uppercase !important;
    color:inherit !important;
    line-height:1.2 !important;
    font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif !important;
}
.bb-card .nn-font-grid{display:grid !important;grid-template-columns:repeat(5,1fr) !important;gap:4px !important;width:100% !important}
.bb-card .nn-row{display:flex !important;gap:10px !important;align-items:flex-end !important;width:100% !important}
.bb-card .nn-row > div{flex:1 1 auto !important;min-width:0 !important}
#btgsb-undo-btn{display:none;align-items:center;gap:5px;height:28px;padding:0 10px;border:1px solid #27267e;border-radius:6px;background:#fff;color:#27267e;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .1s}
#btgsb-undo-btn:hover{background:#27267e;color:#fff}
#btgsb-undo-btn:disabled{opacity:.35;cursor:not-allowed}
#btgsb-undo-btn:disabled:hover{background:#fff;color:#27267e}
#btgsb-design-list{display:flex;flex-direction:column;gap:12px}
.btgsb-card{display:flex;gap:12px;align-items:flex-start;background:#fff;border:1px solid #dde2ef;border-radius:10px;padding:12px;box-shadow:0 1px 4px rgba(0,0,0,.05);position:relative}
.btgsb-card-thumb {
    flex-shrink:0;
    width:72px;
    height:72px;
    border-radius:6px;
    overflow:hidden;
    background:repeating-conic-gradient(#e0e0e0 0% 25%,#fff 0% 50%) 0 0/12px 12px;
    border:1px solid #e0e0e0;
    display:flex;
    align-items:center;
    justify-content:center;
}
.btgsb-card-thumb img{max-width:100%;max-height:100%;object-fit:contain}
.btgsb-card-body{flex:1;min-width:0;display:flex;flex-direction:column;gap:7px}
.btgsb-card-name{font-weight:700;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#111}
.btgsb-card-dpi{display:flex;align-items:center;gap:5px;font-size:12px}
.btgsb-dpi-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.btgsb-dpi-dot.good{background:#27ae60}.btgsb-dpi-dot.fine{background:#e5a000}.btgsb-dpi-dot.bad{background:#c0392b}
.btgsb-dpi-good{color:#27ae60;font-weight:600}.btgsb-dpi-fine{color:#e5a000;font-weight:600}.btgsb-dpi-bad{color:#c0392b;font-weight:700}
.btgsb-card-size{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.btgsb-card-size label{display:flex;align-items:center;gap:4px;font-size:12px;color:#666;font-weight:600}
.btgsb-card-size input{width:62px;border:1px solid #c0c9e8;border-radius:5px;padding:4px 6px;font-size:13px;text-align:center;font-family:inherit;color:#111}
.btgsb-card-size input:focus{outline:none;border-color:#27267e;box-shadow:0 0 0 2px rgba(39,38,126,.12)}
.btgsb-card-size input[readonly]{background:#f4f5fb;color:#888;cursor:default}
.btgsb-size-sep{font-size:16px;color:#bbb;line-height:1}
.btgsb-card-qty{display:flex;align-items:center;gap:6px}
.btgsb-qty-btn {
    width:30px;
    height:30px;
    border:1px solid #c0c9e8;
    border-radius:6px;
    background:#f4f5fb;
    color:#27267e;
    font-size:18px;
    line-height:1;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:0;
    font-family:inherit;
    transition:background .1s;
}
.btgsb-qty-btn:hover{background:#d0cff0}
.btgsb-qty-inp{width:52px;border:1px solid #c0c9e8;border-radius:5px;padding:4px 6px;font-size:15px;text-align:center;font-weight:700;font-family:inherit;color:#111}
.btgsb-qty-inp:focus{outline:none;border-color:#27267e;box-shadow:0 0 0 2px rgba(39,38,126,.12)}
.btgsb-qty-label{font-size:12px;color:#888}
.btgsb-card-remove {
    position:absolute;
    top:10px;
    right:10px;
    background:none;
    border:none;
    color:#c0392b;
    font-size:18px;
    font-weight:700;
    cursor:pointer;
    line-height:1;
    padding:0;
    width:24px;
    height:24px;
    border-radius:4px;
    transition:color .1s,background .1s;
    display:inline-flex;
    align-items:center;
    justify-content:center;
}
.btgsb-card-remove:hover{color:#fff;background:#c0392b}
#btgsb-preview-panel{background:#fff;border:1px solid #dde2ef;border-radius:12px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.06)}
#btgsb-preview-header{display:flex;align-items:center;gap:8px;padding:12px 16px;border-bottom:1px solid #edf0fa;background:#f8f9ff}
#btgsb-preview-label{flex:1;font-size:13px;font-weight:700;color:#333}
#btgsb-mode-label{color:#27267e}
#btgsb-zoom-in,#btgsb-zoom-out {
    width:28px;
    height:28px;
    border:1px solid #c0c9e8;
    border-radius:6px;
    background:#fff;
    color:#27267e;
    font-size:16px;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    font-family:inherit;
    transition:background .1s;
}
#btgsb-zoom-in:hover,#btgsb-zoom-out:hover{background:#f0eff8}
#btgsb-auto-layout-btn {
    display:none;
    align-items:center;
    gap:5px;
    height:28px;
    padding:0 10px;
    border:1px solid #27267e;
    border-radius:6px;
    background:#fff;
    color:#27267e;
    font-size:12px;
    font-weight:700;
    cursor:pointer;
    font-family:inherit;
    transition:background .1s;
}
#btgsb-auto-layout-btn:hover{background:#27267e;color:#fff}
#btgsb-inspector {
    display:none !important;
    align-items:center !important;
    gap:10px !important;
    padding:8px 14px !important;
    border-bottom:1px solid #edf0fa !important;
    background:#fafbff !important;
    font-size:13px !important;
    color:#333 !important;
    flex-wrap:wrap !important;
}
#btgsb-inspector.bi-show{display:flex !important}
#btgsb-inspector .bi-label{font-size:11px !important;font-weight:700 !important;color:#888 !important;text-transform:uppercase !important;letter-spacing:.04em !important;flex-shrink:0 !important}
#btgsb-inspector .bi-name{font-weight:700 !important;color:#27267e !important;max-width:240px !important;overflow:hidden !important;text-overflow:ellipsis !important;white-space:nowrap !important;min-width:0 !important}
#btgsb-inspector .bi-spacer{flex:1 1 auto !important;min-width:6px !important}
#btgsb-inspector .bi-field{display:inline-flex !important;align-items:center !important;gap:4px !important;flex-shrink:0 !important;margin:0 !important;padding:0 !important;background:transparent !important;border:none !important}
#btgsb-inspector .bi-key{font-size:11px !important;font-weight:700 !important;color:#999 !important}
#btgsb-inspector .bi-field input {
    width:64px !important;
    box-sizing:border-box !important;
    border:1px solid #c0c9e8 !important;
    border-radius:5px !important;
    padding:5px 7px !important;
    font-size:13px !important;
    font-family:inherit !important;
    color:#111 !important;
    background:#fff !important;
    text-align:center !important;
    height:auto !important;
    line-height:normal !important;
    -webkit-appearance:none !important;
    -moz-appearance:textfield !important;
    appearance:textfield !important;
}
#btgsb-inspector .bi-field input:focus{outline:none !important;border-color:#27267e !important;box-shadow:0 0 0 2px rgba(39,38,126,.12) !important}
#btgsb-inspector .bi-unit{font-size:11px !important;color:#aaa !important}
#btgsb-inspector .bi-lock {
    flex-shrink:0 !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    width:30px !important;
    height:30px !important;
    min-width:30px !important;
    border:1px solid #dde2ef !important;
    border-radius:5px !important;
    background:#fff !important;
    color:#bbb !important;
    font-size:14px !important;
    cursor:pointer !important;
    font-family:inherit !important;
    padding:0 !important;
    line-height:1 !important;
    box-shadow:none !important;
    text-transform:none !important;
    letter-spacing:normal !important;
    font-weight:400 !important;
}
#btgsb-inspector .bi-lock.bi-locked{border-color:#27267e !important;color:#27267e !important;background:#f0eff8 !important}
#btgsb-inspector .bi-lock:hover{border-color:#27267e !important}
#btgsb-canvas-wrap{overflow:auto;padding:16px;min-height:200px;max-height:600px;position:relative;background:#f0f2fa}
#btgsb-canvas{display:block;box-shadow:0 2px 12px rgba(0,0,0,.12)}
#btgsb-canvas-placeholder{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#bbb;font-size:13px;text-align:center;padding:20px;line-height:1.6;pointer-events:none}
#btgsb-drag-tooltip {
    position:absolute;
    display:none;
    pointer-events:none;
    background:#27267e;
    color:#fff;
    font-size:12px;
    font-weight:700;
    padding:5px 9px;
    border-radius:5px;
    z-index:100;
    font-family:inherit;
    white-space:nowrap;
    box-shadow:0 2px 8px rgba(0,0,0,.2);
}
#btgsb-summary{background:#fff;border:1px solid #dde2ef;border-radius:12px;padding:18px;box-shadow:0 1px 6px rgba(0,0,0,.06)}
.bsum-row{display:flex;justify-content:space-between;align-items:flex-start;padding:7px 0;border-bottom:1px solid #f0f2f8;font-size:13px;gap:12px}
.bsum-row:last-of-type{border-bottom:none}
.bsum-row span{color:#777}.bsum-row strong{color:#111;font-weight:700;text-align:right}
.bsum-tier{font-size:11px!important;color:#27267e!important}
.bsum-total{margin-top:4px;padding-top:12px!important;border-top:2px solid #d0cff0!important;border-bottom:none!important}
.bsum-total span{font-size:15px;font-weight:700;color:#333}
.bsum-total strong{font-size:28px!important;color:#27267e!important}
#btgsb-order-btn {
    display:block;
    width:100%;
    margin-top:14px;
    padding:14px;
    border:none;
    border-radius:8px;
    color:#fff;
    font-size:16px;
    font-weight:800;
    cursor:pointer;
    letter-spacing:.3px;
    transition:opacity .15s,filter .15s;
    font-family:inherit;
}
#btgsb-order-btn:hover:not(:disabled){filter:brightness(1.1)}
#btgsb-order-btn:disabled{opacity:.4;cursor:not-allowed}
.btgsb-hint{font-size:11px;color:#aaa;margin:6px 0 0;line-height:1.5}
@media(max-width:900px){#btgsb-app{flex-direction:column}#btgsb-left,#btgsb-right{width:100%;position:static;flex:none}}

/* --- Generate mode toggle (Both / Names / Numbers) --- */
.nn-mode-grid{display:grid !important;grid-template-columns:repeat(3,1fr) !important;gap:4px !important;width:100% !important}
.nn-mode-btn{padding:7px 4px !important;border:1px solid #dde2ef !important;border-radius:6px !important;background:#fff !important;cursor:pointer !important;font-size:12px !important;font-weight:700 !important;color:#555 !important;font-family:inherit !important;line-height:1.2 !important;text-transform:none !important;letter-spacing:normal !important;box-shadow:none !important;width:auto !important;height:auto !important}
.nn-mode-btn:hover{border-color:#27267e !important}
.nn-mode-btn.nn-active{border-color:#27267e !important;background:#f0eff8 !important;color:#27267e !important;box-shadow:inset 0 0 0 1px #27267e !important}
#btgsb-roster-grid.nn-mode-names .nn-row-number{display:none !important}
#btgsb-roster-grid.nn-mode-numbers .nn-row-name{display:none !important}
#btgsb-roster-grid.nn-mode-numbers .nn-row-number{flex:1 1 auto !important;width:auto !important;min-width:0 !important;max-width:none !important;text-align:left !important}
#btgsb-namesnum-body.nn-gen-numbers .nn-name-h-wrap{display:none !important}
#btgsb-namesnum-body.nn-gen-names .nn-number-h-wrap{display:none !important}

/* --- Hex / CMYK color control --- */
.bt-color{display:flex !important;flex-direction:column !important;gap:7px !important;width:100% !important}
.bt-color-top{display:flex !important;align-items:center !important;gap:8px !important}
.bt-color .bt-native{-webkit-appearance:none !important;-moz-appearance:none !important;appearance:none !important;width:36px !important;height:34px !important;min-width:36px !important;padding:2px !important;border:1px solid #c0c9e8 !important;border-radius:6px !important;background:#fff !important;cursor:pointer !important;box-shadow:none !important}
.bt-color .bt-native::-webkit-color-swatch-wrapper{padding:0 !important}
.bt-color .bt-native::-webkit-color-swatch{border:none !important;border-radius:4px !important}
.bt-color .bt-native::-moz-color-swatch{border:none !important;border-radius:4px !important}
.bt-color-modes{display:inline-flex !important;border:1px solid #dde2ef !important;border-radius:6px !important;overflow:hidden !important}
.bt-color .bt-cmode{padding:6px 12px !important;border:none !important;border-right:1px solid #dde2ef !important;background:#fff !important;cursor:pointer !important;font-size:12px !important;font-weight:700 !important;color:#777 !important;font-family:inherit !important;line-height:1.2 !important;text-transform:none !important;letter-spacing:normal !important;box-shadow:none !important;width:auto !important;height:auto !important;border-radius:0 !important}
.bt-color .bt-cmode:last-child{border-right:none !important}
.bt-color .bt-cmode.active{background:#27267e !important;color:#fff !important}
.bt-pane{display:flex !important;align-items:center !important;gap:6px !important;width:100% !important}
.bt-color .bt-hex-input{flex:1 1 auto !important;box-sizing:border-box !important;border:1px solid #c0c9e8 !important;border-radius:6px !important;padding:7px 9px !important;font-size:13px !important;font-family:'SF Mono','Monaco','Consolas',monospace !important;color:#111 !important;background:#fff !important;text-transform:lowercase !important;letter-spacing:.02em !important;width:auto !important;height:auto !important;box-shadow:none !important}
.bt-color .bt-hex-input:focus{outline:none !important;border-color:#27267e !important;box-shadow:0 0 0 2px rgba(39,38,126,.12) !important}
.bt-pane-cmyk{display:flex !important;gap:6px !important}
.bt-pane-cmyk label{flex:1 1 0 !important;display:flex !important;flex-direction:column !important;align-items:center !important;gap:2px !important;min-width:0 !important}
.bt-color .bt-pane-cmyk input{width:100% !important;box-sizing:border-box !important;border:1px solid #c0c9e8 !important;border-radius:6px !important;padding:6px 4px !important;font-size:13px !important;font-family:inherit !important;color:#111 !important;background:#fff !important;text-align:center !important;height:auto !important;-moz-appearance:textfield !important;box-shadow:none !important}
.bt-color .bt-pane-cmyk input:focus{outline:none !important;border-color:#27267e !important;box-shadow:0 0 0 2px rgba(39,38,126,.12) !important}
.bt-pane-cmyk label span{font-size:10px !important;font-weight:700 !important;color:#999 !important;letter-spacing:.05em !important}
</style>

<div id="btgsb-app">
  <div id="btgsb-left">
    <label id="btgsb-upload-zone" for="btgsb-file-input" tabindex="0">
      <div class="btgsb-upload-icon">
        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
      </div>
      <p><strong>Click or drag your artwork here</strong></p>
      <p class="btgsb-hint"><strong>PNG or PDF</strong> &nbsp;&middot;&nbsp; background must be transparent</p>
      <p class="btgsb-hint" style="margin-top:6px;color:#c0392b;font-weight:700">&#9888; JPG files are NOT accepted &mdash; DTF requires transparent backgrounds</p>
      <p class="btgsb-hint" style="margin-top:6px;color:#e5a000;font-weight:600">Art should be 300 DPI or higher at your desired print size</p>
      <input type="file" id="btgsb-file-input" multiple accept=".png,image/png,.pdf,application/pdf">
    </label>
    <div id="btgsb-namesnum-panel">
      <div id="btgsb-namesnum-header">
        <span class="nn-icon">&#9000;</span>
        <span>Names &amp; Numbers</span>
        <span class="nn-caret">&#9654;</span>
      </div>
      <div id="btgsb-namesnum-body" class="nn-gen-both">
        <div>
          <label class="nn-label">What to generate</label>
          <div class="nn-mode-grid">
            <button type="button" class="nn-mode-btn nn-active" data-nnmode="both">Names &amp; Numbers</button>
            <button type="button" class="nn-mode-btn" data-nnmode="names">Names only</button>
            <button type="button" class="nn-mode-btn" data-nnmode="numbers">Numbers only</button>
          </div>
        </div>
        <div>
          <label class="nn-label nn-roster-label">Roster &mdash; name and number per row</label>
          <div id="btgsb-roster-grid" class="nn-mode-both"></div>
          <button type="button" id="btgsb-roster-add" class="nn-add-btn">+ Add row</button>
        </div>
        <div>
          <label class="nn-label">Font style</label>
          <div class="nn-font-grid">
            <button type="button" class="nn-font-btn nn-active" data-font="varsity" style="font-family:'DTFS_Varsity',serif">
              <span class="nn-font-sample" style="font-family:'DTFS_Varsity',serif">Aa</span>
              <span class="nn-font-name">Varsity</span>
            </button>
            <button type="button" class="nn-font-btn" data-font="athletic" style="font-family:'DTFS_Athletic',sans-serif">
              <span class="nn-font-sample" style="font-family:'DTFS_Athletic',sans-serif">Aa</span>
              <span class="nn-font-name">Athletic</span>
            </button>
            <button type="button" class="nn-font-btn" data-font="college" style="font-family:'DTFS_College',serif">
              <span class="nn-font-sample" style="font-family:'DTFS_College',serif">Aa</span>
              <span class="nn-font-name">College</span>
            </button>
            <button type="button" class="nn-font-btn" data-font="block" style="font-family:'Passion One',sans-serif;font-weight:900">
              <span class="nn-font-sample" style="font-family:'Passion One',sans-serif;font-weight:900">Aa</span>
              <span class="nn-font-name">Block</span>
            </button>
            <button type="button" class="nn-font-btn" data-font="impact" style="font-family:Impact,'Haettenschweiler','Arial Narrow Bold',sans-serif">
              <span class="nn-font-sample" style="font-family:Impact,'Haettenschweiler','Arial Narrow Bold',sans-serif">Aa</span>
              <span class="nn-font-name">Impact</span>
            </button>
          </div>
        </div>
        <div class="nn-row">
          <div class="nn-name-h-wrap">
            <label class="nn-label" for="btgsb-namesnum-name-height">Name height (in)</label>
            <input id="btgsb-namesnum-name-height" type="number" step="0.25" min="0.5" max="14" value="2.5">
          </div>
          <div class="nn-number-h-wrap">
            <label class="nn-label" for="btgsb-namesnum-number-height">Number height (in)</label>
            <input id="btgsb-namesnum-number-height" type="number" step="0.25" min="0.5" max="14" value="8">
          </div>
        </div>
        <div>
          <label class="nn-label">Color</label>
          <div id="btgsb-namesnum-color-mount"></div>
        </div>
        <button type="button" id="btgsb-namesnum-generate">Generate Pieces</button>
        <p id="btgsb-namesnum-status"></p>
      </div>
    </div>
    <div id="btgsb-batch-list"></div>
    <div id="btgsb-design-list"></div>
    <div id="btgsb-empty-hint">Upload artwork above, or generate names &amp; numbers from your roster &mdash; set the print size and quantity for each piece, and we'll build the sheet automatically.</div>
  </div>
  <div id="btgsb-right">
    <div id="btgsb-preview-panel">
      <div id="btgsb-preview-header">
        <span id="btgsb-preview-label">DTF Studio &nbsp;&middot;&nbsp; <?php echo $w; ?>" wide &mdash; <span id="btgsb-mode-label">auto-nested</span></span>
        <button id="btgsb-undo-btn" type="button" title="Undo (Ctrl+Z)"><span style="font-size:14px;line-height:1">&#x21A9;</span> Undo</button>
        <button id="btgsb-auto-layout-btn" type="button" title="Re-run auto layout from scratch"><span style="font-size:14px;line-height:1">&#x21BB;</span> Auto Layout</button>
        <button id="btgsb-zoom-out" title="Zoom out">&minus;</button>
        <button id="btgsb-zoom-in"  title="Zoom in">+</button>
      </div>
      <div id="btgsb-inspector">
        <span class="bi-label">Selected:</span>
        <span class="bi-name" id="btgsb-inspector-name">&mdash;</span>
        <span class="bi-spacer"></span>
        <label class="bi-field">
          <span class="bi-key">W</span>
          <input type="number" id="btgsb-inspector-w" step="0.01" min="0.25" inputmode="decimal">
          <span class="bi-unit">in</span>
        </label>
        <label class="bi-field">
          <span class="bi-key">H</span>
          <input type="number" id="btgsb-inspector-h" step="0.01" min="0.25" inputmode="decimal">
          <span class="bi-unit">in</span>
        </label>
        <button type="button" id="btgsb-inspector-lock" class="bi-lock" title="Lock aspect ratio">&#x1F512;</button>
      </div>
      <div id="btgsb-canvas-wrap">
        <canvas id="btgsb-canvas"></canvas>
        <div id="btgsb-canvas-placeholder">Your sheet preview will appear here once you add designs above.</div>
        <div id="btgsb-drag-tooltip"></div>
      </div>
    </div>
    <div id="btgsb-summary">
      <div class="bsum-row"><span>Sheet size <span style="color:#888;font-size:11px;font-weight:400">(<?php echo (int)$s['start_height']; ?>" minimum sheet length)</span></span>   <strong id="bsum-size">&mdash;</strong></div>
      <div class="bsum-row"><span>Total area</span>   <strong id="bsum-area">&mdash;</strong></div>
      <div class="bsum-row"><span>Total pieces</span> <strong id="bsum-pieces">&mdash;</strong></div>
      <div class="bsum-row"><span>Pricing tier</span> <strong id="bsum-tier" class="bsum-tier">&mdash;</strong></div>
      <div class="bsum-row bsum-total"><span>Total</span><strong id="bsum-price">$0.00</strong></div>
      <button id="btgsb-save-btn" type="button" style="display:block;width:100%;margin-bottom:8px;padding:10px;border:1px solid #27267e;border-radius:8px;background:#fff;color:#27267e;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s">&#128190; Save This Sheet</button>
      <button id="btgsb-order-btn" style="background:<?php echo $btnc; ?>" disabled><?php echo $btn; ?></button>
      <div id="btgsb-save-modal" style="display:none;margin-top:10px;background:#f0eff8;border:1px solid #d0cff0;border-radius:8px;padding:12px;font-size:13px">
        <p style="margin:0 0 8px;font-weight:700;color:#27267e">Save this sheet to your browser</p>
        <p style="margin:0 0 8px;font-size:12px;color:#666;line-height:1.4">It'll reload automatically next time you open the builder in this browser on this device.</p>
        <button id="btgsb-save-submit" type="button" style="width:100%;padding:8px;background:#27267e;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit">Save to This Browser</button>
        <div id="btgsb-save-status" style="margin-top:6px;font-size:12px;color:#27267e"></div>
      </div>
      <div id="btgsb-status"></div>
      <p class="btgsb-hint" style="text-align:center;margin-top:8px">Priced on total sheet area (<?php echo $w; ?>" &times; height used) &nbsp;&middot;&nbsp; <span style="color:#27267e;font-weight:700">DTF Studio</span></p>
    </div>
  </div>
</div>

<script>
var BTGSB = <?php echo $config; ?>;

jQuery(function($){
    'use strict';

    if (document.fonts && document.fonts.load) {
        document.fonts.load("12px 'DTFS_Varsity'");
        document.fonts.load("12px 'DTFS_Athletic'");
        document.fonts.load("12px 'DTFS_College'");
        document.fonts.load("12px 'Passion One'");
    }

    var S          = BTGSB.settings;
    var PX_PER_IN  = 36;
    var SHEET_W    = parseFloat(S.canvas_width  || 22);
    var START_H    = parseFloat(S.start_height  || 12);
    var MAX_H      = parseFloat(S.max_height    || 300);
    var MOQ        = parseFloat(S.moq_sq_in     || 264);
    var MARGIN     = parseFloat(S.margin        || 0.15);
    var PADDING    = parseFloat(S.padding       || 0.20);
    var DPI_GOOD   = parseInt(S.dpi_good        || 300);
    var DPI_FINE   = parseInt(S.dpi_fine        || 200);
    var EXPORT_DPI = parseInt(S.export_dpi      || 200);
    var TIERS      = S.pricing_tiers            || [];

    var NN_RENDER_DPI = 200;

    var NN_FONTS = {
        varsity:  { family: "'DTFS_Varsity', serif",                                       weight: '400', label: 'Varsity'  },
        athletic: { family: "'DTFS_Athletic', sans-serif",                                 weight: '400', label: 'Athletic' },
        college:  { family: "'DTFS_College', serif",                                       weight: '400', label: 'College'  },
        block:    { family: "'Passion One', sans-serif",                                   weight: '900', label: 'Block'    },
        impact:   { family: "Impact, 'Haettenschweiler', 'Arial Narrow Bold', sans-serif", weight: '400', label: 'Impact'   }
    };

    var RULER_PX = 24;
    var RESIZE_EDGE_IN = 0.18;

    var designs = [];
    var nextId  = 0;
    var zoom = 1.0;

    var batches = [];
    var nextBatchId = 0;
    var nextBatchItemId = 0;
    var nextBatchNameCounter = 1;

    var undoStack = [];
    var UNDO_LIMIT = 20;
    var isRestoring = false;

    var sheetState = {
        placements: [],
        sheetH: START_H
    };

    var dragState = null;
    var selectedPlacement = null;

    function calcZoom() {
        var wrap = document.getElementById('btgsb-canvas-wrap');
        if (!wrap) return 0.75;
        return Math.max(0.25, Math.min(1.5, (wrap.clientWidth - 32 - RULER_PX) / (SHEET_W * PX_PER_IN)));
    }
    zoom = calcZoom();
    jQuery(window).on('resize', function(){ zoom = calcZoom(); refresh(); });

    var previewCanvas = document.getElementById('btgsb-canvas');
    var previewCtx    = previewCanvas.getContext('2d');

    /* -- Pricing -------------------------------------------------- */
    function getRate(sq) {
        for (var i=0; i<TIERS.length; i++)
            if (sq <= parseFloat(TIERS[i].max)) return parseFloat(TIERS[i].rate);
        return TIERS.length ? parseFloat(TIERS[TIERS.length-1].rate) : 0.02;
    }
    function calcPrice(sq) { return sq * getRate(sq); }
    function tierLabel(sq) {
        for (var i=0; i<TIERS.length; i++) {
            var prev = i===0 ? 0 : parseFloat(TIERS[i-1].max);
            if (sq <= parseFloat(TIERS[i].max)) {
                var maxStr = parseFloat(TIERS[i].max) >= 9000 ? '\u221E' : parseFloat(TIERS[i].max).toLocaleString();
                return prev.toLocaleString()+'\u2013'+maxStr+' sq in @ $'+parseFloat(TIERS[i].rate).toFixed(3)+'/sq in';
            }
        }
        return 'Max tier';
    }

    /* -- Transparency detection ----------------------------------- */
    function imageHasTransparency(img) {
        try {
            var c = document.createElement('canvas');
            c.width  = img.naturalWidth;
            c.height = img.naturalHeight;
            var ctx = c.getContext('2d');
            ctx.drawImage(img, 0, 0);
            var data = ctx.getImageData(0, 0, c.width, c.height).data;
            var total = data.length / 4;
            var stride = Math.max(1, Math.floor(total / 2000));
            for (var i = 3; i < data.length; i += 4 * stride) {
                if (data[i] < 255) return true;
            }
            return false;
        } catch(e) {
            return true;
        }
    }

    function canvasHasTransparentPixels(canvas) {
        try {
            var W = canvas.width, H = canvas.height;
            if (W < 100 || H < 100) return true;
            var ctx = canvas.getContext('2d');
            var regions = [
                [0, 0],
                [W-50, 0],
                [0, H-50],
                [W-50, H-50],
                [Math.floor(W/2)-25, Math.floor(H/2)-25]
            ];
            for (var r = 0; r < regions.length; r++) {
                var data = ctx.getImageData(regions[r][0], regions[r][1], 50, 50).data;
                for (var i = 3; i < data.length; i += 4) {
                    if (data[i] < 255) return true;
                }
            }
            return false;
        } catch(e) {
            return true;
        }
    }

    /* -- PNG DPI metadata injection ------------------------------- */
    var pngCRCTable = null;
    function pngCRC32(bytes) {
        if (!pngCRCTable) {
            pngCRCTable = new Uint32Array(256);
            for (var n = 0; n < 256; n++) {
                var c = n;
                for (var k = 0; k < 8; k++) {
                    c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
                }
                pngCRCTable[n] = c >>> 0;
            }
        }
        var crc = 0xFFFFFFFF;
        for (var i = 0; i < bytes.length; i++) {
            crc = pngCRCTable[(crc ^ bytes[i]) & 0xFF] ^ (crc >>> 8);
        }
        return (crc ^ 0xFFFFFFFF) >>> 0;
    }

    function blobToArrayBuffer(blob) {
        if (typeof blob.arrayBuffer === 'function') return blob.arrayBuffer();
        return new Promise(function(resolve, reject){
            var fr = new FileReader();
            fr.onload  = function(){ resolve(fr.result); };
            fr.onerror = function(){ reject(fr.error); };
            fr.readAsArrayBuffer(blob);
        });
    }

    function injectPngDpi(blob, dpi) {
        return blobToArrayBuffer(blob).then(function(buf){
            var bytes = new Uint8Array(buf);
            if (bytes.length < 33 ||
                bytes[0] !== 0x89 || bytes[1] !== 0x50 || bytes[2] !== 0x4E || bytes[3] !== 0x47 ||
                bytes[4] !== 0x0D || bytes[5] !== 0x0A || bytes[6] !== 0x1A || bytes[7] !== 0x0A) {
                return blob;
            }
            var ihdrEnd = 8 + 25;
            var ppm = Math.round(dpi / 0.0254);
            var chunk = new Uint8Array(21);
            chunk[0] = 0; chunk[1] = 0; chunk[2] = 0; chunk[3] = 9;
            chunk[4] = 0x70; chunk[5] = 0x48; chunk[6] = 0x59; chunk[7] = 0x73;
            chunk[8]  = (ppm >>> 24) & 0xFF;
            chunk[9]  = (ppm >>> 16) & 0xFF;
            chunk[10] = (ppm >>>  8) & 0xFF;
            chunk[11] =  ppm         & 0xFF;
            chunk[12] = (ppm >>> 24) & 0xFF;
            chunk[13] = (ppm >>> 16) & 0xFF;
            chunk[14] = (ppm >>>  8) & 0xFF;
            chunk[15] =  ppm         & 0xFF;
            chunk[16] = 1;
            var crc = pngCRC32(chunk.subarray(4, 17));
            chunk[17] = (crc >>> 24) & 0xFF;
            chunk[18] = (crc >>> 16) & 0xFF;
            chunk[19] = (crc >>>  8) & 0xFF;
            chunk[20] =  crc         & 0xFF;
            var out = new Uint8Array(bytes.length + 21);
            out.set(bytes.subarray(0, ihdrEnd), 0);
            out.set(chunk, ihdrEnd);
            out.set(bytes.subarray(ihdrEnd), ihdrEnd + 21);
            return new Blob([out], {type: 'image/png'});
        });
    }

    /* -- DPI calculation - rotation-aware ------------------------- */
    function computeDPI(d) {
        var effW = d.rotated ? d.nH : d.nW;
        var effH = d.rotated ? d.nW : d.nH;
        var dpiW = effW / Math.max(0.01, d.widthIn);
        var dpiH = effH / Math.max(0.01, d.heightIn);
        return Math.min(dpiW, dpiH);
    }

    /* -- Free-rect splitting (Maximal Rectangles helper) --------- */
    function splitFreeRectsByItem(freeRects, px, py, iw, ih) {
        var newFree = [];
        for (var i=0; i<freeRects.length; i++) {
            var r = freeRects[i];
            if (px+iw <= r.x || px >= r.x+r.w || py+ih <= r.y || py >= r.y+r.h) {
                newFree.push(r);
                continue;
            }
            if (px > r.x)        newFree.push({x:r.x, y:r.y, w:px-r.x, h:r.h});
            if (px+iw < r.x+r.w) newFree.push({x:px+iw, y:r.y, w:(r.x+r.w)-(px+iw), h:r.h});
            if (py > r.y)        newFree.push({x:r.x, y:r.y, w:r.w, h:py-r.y});
            if (py+ih < r.y+r.h) newFree.push({x:r.x, y:py+ih, w:r.w, h:(r.y+r.h)-(py+ih)});
        }
        return newFree.filter(function(a, ai) {
            for (var j=0; j<newFree.length; j++) {
                if (j === ai) continue;
                var b = newFree[j];
                if (b.x<=a.x && b.y<=a.y && b.x+b.w>=a.x+a.w && b.y+b.h>=a.y+a.h) {
                    var dup = (b.x===a.x && b.y===a.y && b.w===a.w && b.h===a.h);
                    if (!dup || j < ai) return false;
                }
            }
            return true;
        });
    }

    function findBestFreeRect(freeRects, iw, ih) {
        var bestScore = Infinity;
        var bestIdx   = -1;
        for (var i=0; i<freeRects.length; i++) {
            var r = freeRects[i];
            if (r.w >= iw - 0.001 && r.h >= ih - 0.001) {
                var s = Math.min(r.w - iw, r.h - ih);
                if (s < bestScore) { bestScore = s; bestIdx = i; }
            }
        }
        return bestIdx;
    }

    // Orientation is decided ONCE PER DESIGN, from that design's own quantity,
    // and never inside the packer. Turning a piece is only a win when it makes
    // the stack of copies SHORTER - fitting more across a row is worthless if
    // it costs more rows. The old version scored each copy against a free rect
    // whose height was MAX_H (300"), so "best short side fit" collapsed into
    // "leftover width" and it turned EVERY tall piece sideways, ignored the
    // customer's Rotate button, and could give two copies of one design two
    // different orientations. A design the customer rotated by hand (rotLock)
    // is never touched.
    function orientationFor(d) {
        var usableW = SHEET_W - PADDING * 2;
        var n = Math.max(1, d.qty || 1);
        function cost(wIn, hIn) {
            if (wIn + MARGIN > usableW + 0.001) return Infinity;
            var across = Math.floor((usableW + MARGIN) / (wIn + MARGIN));
            if (across < 1) return Infinity;
            return Math.ceil(n / across) * (hIn + MARGIN);
        }
        if (d.rotLock) return { w: d.widthIn, h: d.heightIn, rotated: !!d.rotated };
        var flip = cost(d.heightIn, d.widthIn) < cost(d.widthIn, d.heightIn) - 0.001;
        if (!flip) return { w: d.widthIn, h: d.heightIn, rotated: !!d.rotated };
        return { w: d.heightIn, h: d.widthIn, rotated: !d.rotated };
    }

    function nestAroundAnchors(anchored, items) {
        if (!items.length) return [];
        items.sort(function(a,b){ return (b.w*b.h) - (a.w*a.h); });

        var freeRects = [{x:PADDING, y:PADDING, w:SHEET_W - PADDING*2, h:MAX_H}];
        anchored.forEach(function(p){
            freeRects = splitFreeRectsByItem(freeRects, p.x, p.y, p.w + MARGIN, p.h + MARGIN);
        });

        var placed = [];
        items.forEach(function(item) {
            var iw = item.w + MARGIN;
            var ih = item.h + MARGIN;
            var bestIdx = findBestFreeRect(freeRects, iw, ih);
            var px, py;
            if (bestIdx === -1) {
                var maxY = PADDING;
                anchored.forEach(function(pp){ if (pp.y + pp.h + MARGIN > maxY) maxY = pp.y + pp.h + MARGIN; });
                placed.forEach(function(pp){    if (pp.y + pp.h + MARGIN > maxY) maxY = pp.y + pp.h + MARGIN; });
                freeRects.push({x:PADDING, y:maxY, w:SHEET_W - PADDING*2, h:MAX_H});
                bestIdx = findBestFreeRect(freeRects, iw, ih);
                if (bestIdx === -1) {
                    // Wider than the usable sheet even on a fresh full-width row.
                    // Drop it at the bottom-left instead of reading freeRects[-1],
                    // which threw a TypeError and killed the whole render mid-draw,
                    // leaving half-drawn art on the canvas.
                    px = PADDING;
                    py = maxY;
                } else {
                    var chosen0 = freeRects[bestIdx];
                    px = chosen0.x;
                    py = chosen0.y;
                }
            } else {
                var chosen = freeRects[bestIdx];
                px = chosen.x;
                py = chosen.y;
            }
            placed.push({
                designId: item.designId,
                x: px, y: py,
                w: item.w, h: item.h,
                rotated: item.rotated, imgEl: item.imgEl, dpi: item.dpi,
                anchored: false
            });
            freeRects = splitFreeRectsByItem(freeRects, px, py, iw, ih);
        });
        return placed;
    }

    function rectsOverlap(a, b) {
        return a.x < b.x + b.w - 0.001 && a.x + a.w > b.x + 0.001 &&
               a.y < b.y + b.h - 0.001 && a.y + a.h > b.y + 0.001;
    }

    function reconcileSheet() {
        var keepIds = {};
        designs.forEach(function(d){ keepIds[d.id] = true; });

        var groups = {};
        sheetState.placements.forEach(function(p){
            if (!keepIds[p.designId]) return;
            if (!groups[p.designId]) groups[p.designId] = [];
            groups[p.designId].push(p);
        });

        var anchoredPlacements = [];
        var freeItems          = [];

        designs.forEach(function(d){
            var existing = groups[d.id] || [];
            var dpi = computeDPI(d);

            existing.forEach(function(p){
                if (!!p.rotated === !!d.rotated) {
                    p.w = d.widthIn;
                    p.h = d.heightIn;
                } else {
                    p.w = d.heightIn;
                    p.h = d.widthIn;
                }
                p.imgEl = d.imgEl;
                p.dpi = dpi;
            });

            var anch = existing.filter(function(p){ return p.anchored; });
            var free = existing.filter(function(p){ return !p.anchored; });

            var total = anch.length + free.length;
            if (total > d.qty) {
                if (anch.length >= d.qty) {
                    anch.length = d.qty;
                    free = [];
                } else {
                    free.length = d.qty - anch.length;
                }
            }

            var needed = d.qty - anch.length - free.length;
            for (var i = 0; i < needed; i++) {
                free.push({
                    _newItem: true,
                    designId: d.id, w: d.widthIn, h: d.heightIn,
                    rotated: !!d.rotated, imgEl: d.imgEl, dpi: dpi
                });
            }

            anch.forEach(function(p){ anchoredPlacements.push(p); });
            // Every unpinned copy is re-nested from the design's own orientation
            // decision, not from whatever the last pass happened to leave on the
            // placement. Feeding the previous result back in is what let an
            // auto-rotation persist and fight the customer's Rotate button.
            var o = orientationFor(d);
            free.forEach(function(p){
                freeItems.push({
                    designId: d.id, w: o.w, h: o.h,
                    rotated: o.rotated, imgEl: d.imgEl, dpi: dpi
                });
            });
        });

        // Drop stale pins. A pinned piece keeps its x/y forever, but its w/h
        // track the design - so resizing/rotating ANY design can leave pinned
        // neighbors overlapping (this is exactly how order 4953 printed three
        // designs on top of each other). Any pinned piece that now collides
        // with another pinned piece, or hangs off the sheet, loses its pin
        // and gets re-nested into free space. The most recently dragged
        // piece wins conflicts so a fresh drop never gets yanked away.
        anchoredPlacements.sort(function(a, b){
            return (b === selectedPlacement ? 1 : 0) - (a === selectedPlacement ? 1 : 0);
        });
        var anchoredKept = [];
        anchoredPlacements.forEach(function(p){
            var bad = (p.x < -0.001) || (p.y < -0.001) || (p.x + p.w > SHEET_W + 0.001);
            if (!bad) {
                for (var oi = 0; oi < anchoredKept.length; oi++) {
                    if (rectsOverlap(p, anchoredKept[oi])) { bad = true; break; }
                }
            }
            if (bad) {
                p.anchored = false;
                freeItems.push({
                    designId: p.designId, w: p.w, h: p.h,
                    rotated: p.rotated, imgEl: p.imgEl, dpi: p.dpi
                });
            } else {
                anchoredKept.push(p);
            }
        });

        var newFreePlacements = nestAroundAnchors(anchoredKept, freeItems);

        var combined = anchoredKept.concat(newFreePlacements);
        var orderMap = {};
        designs.forEach(function(d, idx){ orderMap[d.id] = idx; });
        combined.sort(function(a, b){
            return ((orderMap[a.designId] !== undefined) ? orderMap[a.designId] : 999)
                 - ((orderMap[b.designId] !== undefined) ? orderMap[b.designId] : 999);
        });

        sheetState.placements = combined;

        if (selectedPlacement && combined.indexOf(selectedPlacement) === -1) {
            // Free placements are rebuilt as new objects on every reconcile.
            // Re-bind the selection to the matching new placement (same
            // design, same spot) so clicking a piece doesn't lose selection
            // now that clicking no longer pins.
            var rebound = null;
            for (var ri = 0; ri < combined.length; ri++) {
                var rp = combined[ri];
                if (rp.designId === selectedPlacement.designId &&
                    Math.abs(rp.x - selectedPlacement.x) < 0.01 &&
                    Math.abs(rp.y - selectedPlacement.y) < 0.01) { rebound = rp; break; }
            }
            selectedPlacement = rebound;
        }
    }

    function computeSheetH(placements) {
        var maxY = PADDING;
        for (var i=0; i<placements.length; i++) {
            var bottom = placements[i].y + placements[i].h + PADDING;
            if (bottom > maxY) maxY = bottom;
        }
        return Math.max(START_H, maxY);
    }

    function countAnchored() {
        var n = 0;
        for (var i=0; i<sheetState.placements.length; i++) {
            if (sheetState.placements[i].anchored) n++;
        }
        return n;
    }

    function recalculate() {
        reconcileSheet();
        sheetState.sheetH = computeSheetH(sheetState.placements);
    }

    /* -- Rulers -------------------------------------------------- */
    function drawRulers(ctx, canvasW, canvasH, sheetPxW, sheetPxH, dpi) {
        ctx.fillStyle = '#f0f2fa';
        ctx.fillRect(0, 0, canvasW, RULER_PX);
        ctx.fillRect(0, 0, RULER_PX, canvasH);
        ctx.fillStyle = '#e0e5f0';
        ctx.fillRect(0, 0, RULER_PX, RULER_PX);

        ctx.strokeStyle = '#27267e';
        ctx.fillStyle   = '#27267e';
        ctx.font        = 'bold 10px -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif';
        ctx.lineWidth   = 1;

        var inchesW = sheetPxW / dpi;
        var inchesH = sheetPxH / dpi;
        var sheetRight  = RULER_PX + sheetPxW;
        var sheetBottom = RULER_PX + sheetPxH;

        ctx.textBaseline = 'middle';
        for (var i = 0; i <= Math.ceil(inchesW); i++) {
            var x = RULER_PX + i * dpi;
            if (x > sheetRight + 0.5) break;
            ctx.beginPath();
            ctx.moveTo(x + 0.5, RULER_PX);
            ctx.lineTo(x + 0.5, RULER_PX - 8);
            ctx.stroke();
            if (i > 0 && i <= inchesW + 0.001) {
                if (x > sheetRight - 8) {
                    ctx.textAlign = 'right';
                    ctx.fillText(String(i), Math.min(x + 6, canvasW - 1), RULER_PX - 14);
                } else {
                    ctx.textAlign = 'center';
                    ctx.fillText(String(i), x, RULER_PX - 14);
                }
            }
            var hx = x + dpi / 2;
            if (hx < sheetRight + 0.5) {
                ctx.beginPath();
                ctx.moveTo(hx + 0.5, RULER_PX);
                ctx.lineTo(hx + 0.5, RULER_PX - 4);
                ctx.stroke();
            }
        }

        for (var j = 0; j <= Math.ceil(inchesH); j++) {
            var y = RULER_PX + j * dpi;
            if (y > sheetBottom + 0.5) break;
            ctx.beginPath();
            ctx.moveTo(RULER_PX,     y + 0.5);
            ctx.lineTo(RULER_PX - 8, y + 0.5);
            ctx.stroke();
            if (j > 0 && j <= inchesH + 0.001) {
                ctx.save();
                if (y > sheetBottom - 8) {
                    ctx.translate(RULER_PX - 14, Math.min(y + 6, canvasH - 1));
                    ctx.rotate(-Math.PI / 2);
                    ctx.textAlign = 'right';
                    ctx.fillText(String(j), 0, 0);
                } else {
                    ctx.translate(RULER_PX - 14, y);
                    ctx.rotate(-Math.PI / 2);
                    ctx.textAlign = 'center';
                    ctx.fillText(String(j), 0, 0);
                }
                ctx.restore();
            }
            var hy = y + dpi / 2;
            if (hy < sheetBottom + 0.5) {
                ctx.beginPath();
                ctx.moveTo(RULER_PX,     hy + 0.5);
                ctx.lineTo(RULER_PX - 4, hy + 0.5);
                ctx.stroke();
            }
        }

        ctx.strokeStyle = '#c0c9e8';
        ctx.beginPath();
        ctx.moveTo(0, RULER_PX + 0.5);
        ctx.lineTo(sheetRight, RULER_PX + 0.5);
        ctx.moveTo(RULER_PX + 0.5, 0);
        ctx.lineTo(RULER_PX + 0.5, sheetBottom);
        ctx.stroke();
    }

    function drawResizeHandles(ctx, x, y, iW, iH, isDragging) {
        var hSize = 7;
        var hHalf = hSize / 2;
        var pts = [
            [x,         y       ],
            [x + iW/2,  y       ],
            [x + iW,    y       ],
            [x,         y + iH/2],
            [x + iW,    y + iH/2],
            [x,         y + iH  ],
            [x + iW/2,  y + iH  ],
            [x + iW,    y + iH  ]
        ];
        ctx.fillStyle   = isDragging ? '#27267e' : '#ffffff';
        ctx.strokeStyle = '#27267e';
        ctx.lineWidth   = 1.5;
        for (var p = 0; p < pts.length; p++) {
            ctx.fillRect(  pts[p][0] - hHalf, pts[p][1] - hHalf, hSize, hSize);
            ctx.strokeRect(pts[p][0] - hHalf + 0.5, pts[p][1] - hHalf + 0.5, hSize - 1, hSize - 1);
        }
    }

    /* -- Render -------------------------------------------------- */
    function renderSheet(ctx, canvas, placed, sheetH, dpi, showGuides, showRulers) {
        var rulerOffset = showRulers ? RULER_PX : 0;
        var labelPad = showRulers ? 14 : 0;
        var sheetPxW = Math.round(SHEET_W * dpi);
        var sheetPxH = Math.round(sheetH  * dpi);
        var canvasW  = sheetPxW + rulerOffset + labelPad;
        var canvasH  = sheetPxH + rulerOffset + labelPad;
        canvas.width  = canvasW;
        canvas.height = canvasH;

        ctx.clearRect(0, 0, canvasW, canvasH);

        ctx.save();
        ctx.translate(rulerOffset, rulerOffset);

        if (showGuides) {
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, sheetPxW, sheetPxH);
            ctx.fillStyle = 'rgba(160,160,200,0.6)';
            for (var gx=dpi; gx<sheetPxW; gx+=dpi) {
                for (var gy=dpi; gy<sheetPxH; gy+=dpi) {
                    ctx.beginPath(); ctx.arc(gx, gy, 1.2, 0, Math.PI*2); ctx.fill();
                }
            }
        }

        placed.forEach(function(item) {
            var x  = Math.round(item.x * dpi);
            var y  = Math.round(item.y * dpi);
            var iW = Math.round(item.w * dpi);
            var iH = Math.round(item.h * dpi);
            if (item.rotated) {
                ctx.save();
                ctx.translate(x + iW/2, y + iH/2);
                ctx.rotate(Math.PI/2);
                ctx.drawImage(item.imgEl, -iH/2, -iW/2, iH, iW);
                ctx.restore();
            } else {
                ctx.drawImage(item.imgEl, x, y, iW, iH);
            }
            if (showGuides) {
                var d = item.dpi || 999;
                var isDragging = dragState && dragState.placement === item;
                var isSelected = selectedPlacement === item;
                ctx.strokeStyle = (isDragging || isSelected) ? '#27267e' : (d >= DPI_GOOD ? '#27ae60' : d >= DPI_FINE ? '#e5a000' : '#c0392b');
                ctx.lineWidth   = isDragging ? 4 : (isSelected ? 3 : 2);
                ctx.strokeRect(x+1.5, y+1.5, iW-3, iH-3);
            }
        });

        if (showGuides) {
            placed.forEach(function(item) {
                var x  = Math.round(item.x * dpi);
                var y  = Math.round(item.y * dpi);
                var iW = Math.round(item.w * dpi);
                var iH = Math.round(item.h * dpi);

                if (selectedPlacement === item) {
                    drawResizeHandles(ctx, x, y, iW, iH, dragState && dragState.placement === item);
                }

                if (item.anchored) {
                    var pinX = x + iW - 9;
                    var pinY = y + 9;
                    ctx.fillStyle   = '#27267e';
                    ctx.beginPath();
                    ctx.arc(pinX, pinY, 7, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.fillStyle   = '#ffffff';
                    ctx.beginPath();
                    ctx.arc(pinX, pinY, 2.5, 0, Math.PI * 2);
                    ctx.fill();
                }
            });

            ctx.strokeStyle = '#27267e';
            ctx.lineWidth   = 2;
            ctx.strokeRect(1, 1, sheetPxW-2, sheetPxH-2);
        }

        ctx.restore();

        if (showRulers) drawRulers(ctx, canvasW, canvasH, sheetPxW, sheetPxH, dpi);
    }

    /* -- Refresh ------------------------------------------------- */
    function refresh() {
        recalculate();
        var placed     = sheetState.placements;
        var sheetH     = sheetState.sheetH;
        var totalPieces= placed.length;
        var hasDesigns = totalPieces > 0;

        $('#btgsb-canvas-placeholder').toggle(!hasDesigns);
        $('#btgsb-canvas').toggle(hasDesigns);

        var anchorCount = countAnchored();
        if (anchorCount > 0) {
            $('#btgsb-mode-label').html('auto-nested &nbsp;&middot;&nbsp; <span style="color:#27267e">' + anchorCount + ' pinned</span>');
            $('#btgsb-auto-layout-btn').css('display', hasDesigns ? 'inline-flex' : 'none');
        } else {
            $('#btgsb-mode-label').text('auto-nested');
            $('#btgsb-auto-layout-btn').css('display', 'none');
        }

        if (hasDesigns) {
            renderSheet(previewCtx, previewCanvas, placed, sheetH, PX_PER_IN * zoom, true, true);
        }

        var sqIn  = Math.max(MOQ, SHEET_W * sheetH);
        var price = calcPrice(sqIn);

        $('#bsum-size').text(hasDesigns ? SHEET_W+'" \u00D7 '+sheetH.toFixed(2)+'"' : '\u2014');
        $('#bsum-area').text(hasDesigns ? Math.round(sqIn).toLocaleString()+' sq in' : '\u2014');
        $('#bsum-pieces').text(hasDesigns ? totalPieces : '\u2014');
        $('#bsum-tier').text(hasDesigns ? tierLabel(sqIn) : '\u2014');
        $('#bsum-price').text(hasDesigns ? '$'+price.toFixed(2) : '$0.00');
        $('#btgsb-order-btn').prop('disabled', !hasDesigns);

        updateCards();
        renderBatchCards();
        updateUndoButton();
        updateInspector();

        window._btgsb = {placed:placed, sheetH:sheetH, totalPieces:totalPieces, sqIn:sqIn, price:price};
        autosaveLocal();
    }

    function buildSavePayload() {
        var anchors = [];
        sheetState.placements.forEach(function(p){
            if (!p.anchored) return;
            var di = -1;
            for (var i=0;i<designs.length;i++) { if (designs[i].id === p.designId) { di = i; break; } }
            if (di === -1) return;
            anchors.push({di:di, x:p.x, y:p.y});
        });
        var savedBatches = batches.map(function(b){
            return {
                id: b.id,
                name: b.name,
                font: b.font,
                color: b.color,
                nameHeight: b.nameHeight,
                numberHeight: b.numberHeight,
                open: b.open,
                items: b.items.map(function(it){
                    var di = -1;
                    for (var i=0;i<designs.length;i++) { if (designs[i].id === it.designId) { di = i; break; } }
                    return {
                        kind: it.kind,
                        text: it.text,
                        di: di,
                        fontOverride: it.fontOverride
                    };
                }).filter(function(it){ return it.di !== -1; })
            };
        });
        return {
            v: 4,
            designs: designs.map(function(d){
                return {
                    name:d.name, dataUrl:d.dataUrl, nW:d.nW, nH:d.nH,
                    widthIn:d.widthIn, heightIn:d.heightIn, qty:d.qty,
                    locked:d.locked, rotated:d.rotated, rotLock:d.rotLock, hasAlpha:d.hasAlpha,
                    batchId: d.batchId || null
                };
            }),
            layout: { anchors: anchors },
            batches: savedBatches,
            nextBatchNameCounter: nextBatchNameCounter
        };
    }

    var _autosaveTimer = null;
    function autosaveLocal() {
        // Debounced + off the synchronous refresh path. buildSavePayload()
        // serializes every design's base64 image, so running it on every
        // refresh during rapid edits was costly; coalesce to once per pause.
        // localStorage may reject very large payloads (quota) - caught below.
        if (_autosaveTimer) clearTimeout(_autosaveTimer);
        _autosaveTimer = setTimeout(function(){
            try {
                localStorage.setItem('btgsb_autosave', JSON.stringify(buildSavePayload()));
            } catch(e){}
        }, 1000);
    }

    /* -- Design Cards -------------------------------------------- */
    function updateCards() {
        var $list = $('#btgsb-design-list');
        $list.empty();
        var loose = designs.filter(function(d){ return !d.batchId; });
        $('#btgsb-empty-hint').toggle(loose.length === 0 && batches.length === 0);

        loose.forEach(function(d, idx) {
            var dpi    = computeDPI(d);
            var dpiCls = dpi >= DPI_GOOD ? 'good' : dpi >= DPI_FINE ? 'fine' : 'bad';
            var dpiLbl = dpi >= DPI_GOOD ? 'Good' : dpi >= DPI_FINE ? 'Fine' : '\u26A0 Low DPI';
            var dispH  = d.heightIn.toFixed(2);

            var dotColor = dpiCls==='good'?'#27ae60':dpiCls==='fine'?'#e5a000':'#c0392b';
            var isLocked   = (d.locked !== false);
            var lockColor  = isLocked ? '#27267e' : '#bbb';
            var lockBorder = isLocked ? '1px solid #27267e' : '1px solid #dde2ef';
            var lockTitle  = isLocked ? 'Locked (proportional)' : 'Unlocked (free resize)';
            var inpS = 'style="width:54px;border:1px solid #c0c9e8;border-radius:5px;padding:3px 5px;font-size:13px;text-align:center;font-family:inherit;color:#111;background:#fff;box-sizing:border-box"';
            var transparencyWarning = (d.hasAlpha === false)
                ? '<div style="background:#fdf0ef;border:1px solid #f5c6c6;border-radius:5px;padding:5px 7px;font-size:11px;color:#c0392b;font-weight:700;line-height:1.3">\u26A0 No transparent background detected &mdash; this design will print with a solid background. Re-export from your design app with "Transparent Background" enabled.</div>'
                : '';
            $list.append(
                '<div data-id="'+d.id+'" style="position:relative;display:flex;align-items:stretch;background:#fff;border:1px solid #dde2ef;border-radius:10px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);margin-bottom:0">'+
                  '<button class="btgsb-card-remove" type="button" title="Remove design" style="position:absolute;top:6px;right:6px;background:none;border:none;color:#c0392b;font-size:18px;font-weight:700;cursor:pointer;padding:0;width:26px;height:26px;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;z-index:2;line-height:1">&#x2715;</button>'+
                  '<div style="width:110px;min-width:110px;background:repeating-conic-gradient(#e0e0e0 0% 25%,#fff 0% 50%) 0 0/10px 10px;border-right:1px solid #eee;display:flex;align-items:center;justify-content:center;padding:6px">'+
                    '<img src="'+d.dataUrl+'" style="max-width:98px;max-height:120px;object-fit:contain;display:block">'+
                  '</div>'+
                  '<div style="flex:1;min-width:0;padding:10px 12px;display:flex;flex-direction:column;gap:7px">'+
                    '<div style="display:flex;align-items:center;gap:4px;padding-right:28px">'+
                      '<span style="font-weight:700;font-size:13px;color:#111;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1">'+esc(d.name)+'</span>'+
                    '</div>'+
                    transparencyWarning +
                    '<div style="display:flex;align-items:center;gap:5px;font-size:12px">'+
                      '<span style="width:7px;height:7px;min-width:7px;border-radius:50%;background:'+dotColor+';display:inline-block"></span>'+
                      '<span style="color:'+dotColor+';font-weight:600">'+Math.round(dpi)+' DPI &mdash; '+dpiLbl+'</span>'+
                    '</div>'+
                    '<div style="display:flex;align-items:center;gap:5px;flex-wrap:nowrap">'+
                      '<span style="font-size:11px;color:#999;font-weight:600">W</span>'+
                      '<input class="btgsb-w-in" type="number" step="0.1" min="0.1" max="'+SHEET_W+'" value="'+d.widthIn.toFixed(2)+'" '+inpS+'>'+
                      '<span style="font-size:12px;color:#ccc">&times;</span>'+
                      '<span style="font-size:11px;color:#999;font-weight:600">H</span>'+
                      '<input class="btgsb-h-in" type="number" step="0.1" min="0.1" value="'+dispH+'" '+inpS+'>'+
                      '<span style="font-size:11px;color:#aaa">in</span>'+
                      '<button class="btgsb-lock-btn" type="button" title="'+lockTitle+'" style="display:inline-flex;align-items:center;justify-content:center;background:none;border:'+lockBorder+';border-radius:5px;width:26px;height:26px;font-size:13px;cursor:pointer;flex-shrink:0;color:'+lockColor+'">'+( isLocked ? '&#x1F512;' : '&#x1F513;' )+'</button>'+
                    '</div>'+
                    '<div style="display:flex;align-items:center;gap:5px">'+
                      '<button class="btgsb-qty-btn btgsb-qty-minus" style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border:1px solid #c0c9e8;border-radius:5px;background:#f4f5fb;color:#27267e;font-size:15px;cursor:pointer;padding:0;flex-shrink:0">&#x2212;</button>'+
                      '<input class="btgsb-qty-inp" type="number" min="1" max="500" value="'+d.qty+'" style="width:60px;border:1px solid #c0c9e8;border-radius:5px;padding:3px 4px;font-size:15px;text-align:center;font-weight:800;font-family:inherit;color:#111;-moz-appearance:textfield">'+
                      '<button class="btgsb-qty-btn btgsb-qty-plus" style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border:1px solid #c0c9e8;border-radius:5px;background:#f4f5fb;color:#27267e;font-size:15px;cursor:pointer;padding:0;flex-shrink:0">+</button>'+
                      '<span style="font-size:11px;color:#aaa;text-transform:uppercase;letter-spacing:.04em">copies</span>'+
                      '<button class="btgsb-rotate-btn" type="button" title="Rotate 90 degrees" style="display:inline-flex;align-items:center;justify-content:center;background:none;border:1px solid #dde2ef;border-radius:5px;height:26px;padding:0 8px;font-size:12px;color:#27267e;cursor:pointer;flex-shrink:0;margin-left:auto;gap:4px;font-weight:600"><span style="font-size:14px;line-height:1">&#x21BB;</span> Rotate</button>'+
                    '</div>'+
                  '</div>'+
                '</div>'
            );
        });
    }

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* -- Card Events --------------------------------------------- */
    $('#btgsb-design-list').on('input change','.btgsb-w-in',function(){
        var id = $(this).closest('[data-id]').data('id');
        var d  = designs.find(function(x){return x.id===id;});
        if (!d) return;
        d.widthIn = Math.max(0.25, Math.min(parseFloat(this.value)||d.widthIn, SHEET_W));
        if (d.locked !== false) {
            var displayHperW = d.rotated ? (d.nW / d.nH) : (d.nH / d.nW);
            d.heightIn = parseFloat((d.widthIn * displayHperW).toFixed(2));
            $(this).closest('[data-id]').find('.btgsb-h-in').val(d.heightIn.toFixed(2));
        }
        refresh();
    });
    $('#btgsb-design-list').on('input change','.btgsb-h-in',function(){
        var id = $(this).closest('[data-id]').data('id');
        var d  = designs.find(function(x){return x.id===id;});
        if (!d) return;
        d.heightIn = Math.max(0.25, parseFloat(this.value)||d.heightIn);
        if (d.locked !== false) {
            var displayWperH = d.rotated ? (d.nH / d.nW) : (d.nW / d.nH);
            d.widthIn = Math.max(0.25, Math.min(parseFloat((d.heightIn * displayWperH).toFixed(2)), SHEET_W));
            $(this).closest('[data-id]').find('.btgsb-w-in').val(d.widthIn.toFixed(2));
        }
        refresh();
    });
    $('#btgsb-design-list').on('click','.btgsb-lock-btn',function(){
        var id = $(this).closest('[data-id]').data('id');
        var d  = designs.find(function(x){return x.id===id;});
        if (!d) return;
        d.locked = (d.locked === false) ? true : false;
        refresh();
    });
    $('#btgsb-design-list').on('click','.btgsb-qty-minus',function(){
        var id = $(this).closest('[data-id]').data('id');
        var d  = designs.find(function(x){return x.id===id;});
        if (!d) return;
        d.qty = Math.max(1, d.qty-1);
        $(this).siblings('.btgsb-qty-inp').val(d.qty);
        refresh();
    });
    $('#btgsb-design-list').on('click','.btgsb-qty-plus',function(){
        var id = $(this).closest('[data-id]').data('id');
        var d  = designs.find(function(x){return x.id===id;});
        if (!d) return;
        d.qty = Math.min(500, d.qty+1);
        $(this).siblings('.btgsb-qty-inp').val(d.qty);
        refresh();
    });
    $('#btgsb-design-list').on('focusin','.btgsb-qty-inp',function(){
        var el = this;
        setTimeout(function(){ el.select(); }, 0);
    });
    $('#btgsb-design-list').on('change','.btgsb-qty-inp',function(){
        var id = $(this).closest('[data-id]').data('id');
        var d  = designs.find(function(x){return x.id===id;});
        if (!d) return;
        d.qty = Math.max(1, Math.min(500, parseInt(this.value,10)||1));
        this.value = d.qty;
        refresh();
    });
    $('#btgsb-design-list').on('keydown','.btgsb-qty-inp',function(e){
        if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
    });
    $('#btgsb-design-list').on('click','.btgsb-rotate-btn',function(){
        var id = $(this).closest('[data-id]').data('id');
        var d  = designs.find(function(x){return x.id===id;});
        if (!d) return;
        var w = d.widthIn; d.widthIn = d.heightIn; d.heightIn = w;
        d.rotated = !d.rotated;
        // The customer chose this orientation. Lock it so auto-nesting can
        // never quietly turn it back - that is what made this button look
        // like it did nothing.
        d.rotLock = true;
        refresh();
    });
    $('#btgsb-design-list').on('click','.btgsb-card-remove',function(){
        var id = $(this).closest('[data-id]').data('id');
        var d  = designs.find(function(x){return x.id===id;});
        if (!d) return;
        if (!confirm('Remove "'+d.name+'" from this sheet?\n\nUndo with Ctrl+Z if needed.')) return;
        pushUndoSnapshot();
        var idx = designs.findIndex(function(x){return x.id===id;});
        if (idx !== -1) designs.splice(idx,1);
        refresh();
    });

    /* -- Color helpers: Hex + CMYK (screen preview is sRGB) ------ */
    function normHex(v){
        v = String(v || '').trim();
        if (v.charAt(0) !== '#') v = '#' + v;
        if (/^#[0-9a-fA-F]{3}$/.test(v)) v = '#'+v[1]+v[1]+v[2]+v[2]+v[3]+v[3];
        return /^#[0-9a-fA-F]{6}$/.test(v) ? v.toLowerCase() : null;
    }
    function clamp100(v){ v = parseInt(v, 10); if (isNaN(v)) v = 0; return Math.max(0, Math.min(100, v)); }
    function hexToRgb(hex){
        hex = normHex(hex) || '#000000';
        return { r:parseInt(hex.substr(1,2),16), g:parseInt(hex.substr(3,2),16), b:parseInt(hex.substr(5,2),16) };
    }
    function rgbToHex(r,g,b){
        function h(n){ n = Math.max(0, Math.min(255, Math.round(n))); var s = n.toString(16); return s.length < 2 ? '0'+s : s; }
        return '#' + h(r) + h(g) + h(b);
    }
    function cmykToRgb(c,m,y,k){
        c/=100; m/=100; y/=100; k/=100;
        return { r:255*(1-c)*(1-k), g:255*(1-m)*(1-k), b:255*(1-y)*(1-k) };
    }
    function rgbToCmyk(r,g,b){
        var rr=r/255, gg=g/255, bb=b/255;
        var k = 1 - Math.max(rr, gg, bb);
        if (k >= 1) return { c:0, m:0, y:0, k:100 };
        return {
            c: Math.round((1-rr-k)/(1-k)*100),
            m: Math.round((1-gg-k)/(1-k)*100),
            y: Math.round((1-bb-k)/(1-k)*100),
            k: Math.round(k*100)
        };
    }

    function btColorMarkup(hex, target, bid){
        hex = normHex(hex) || '#000000';
        var rgb = hexToRgb(hex);
        var cmyk = rgbToCmyk(rgb.r, rgb.g, rgb.b);
        var dataBid = (bid !== undefined && bid !== null) ? (' data-bid="'+bid+'"') : '';
        return '' +
        '<div class="bt-color" data-hex="'+hex+'" data-target="'+target+'"'+dataBid+'>' +
          '<div class="bt-color-top">' +
            '<input type="color" class="bt-native" value="'+hex+'">' +
            '<div class="bt-color-modes">' +
              '<button type="button" class="bt-cmode active" data-cmode="hex">Hex</button>' +
              '<button type="button" class="bt-cmode" data-cmode="cmyk">CMYK</button>' +
            '</div>' +
          '</div>' +
          '<div class="bt-pane bt-pane-hex">' +
            '<input type="text" class="bt-hex-input" maxlength="7" spellcheck="false" value="'+hex+'">' +
          '</div>' +
          '<div class="bt-pane bt-pane-cmyk" style="display:none">' +
            '<label><input type="number" class="bt-c" min="0" max="100" value="'+cmyk.c+'"><span>C</span></label>' +
            '<label><input type="number" class="bt-m" min="0" max="100" value="'+cmyk.m+'"><span>M</span></label>' +
            '<label><input type="number" class="bt-y" min="0" max="100" value="'+cmyk.y+'"><span>Y</span></label>' +
            '<label><input type="number" class="bt-k" min="0" max="100" value="'+cmyk.k+'"><span>K</span></label>' +
          '</div>' +
        '</div>';
    }
    function btColorGet($wrap){ return normHex($wrap.attr('data-hex')) || '#000000'; }
    function btColorSet($wrap, hex){
        hex = normHex(hex);
        if (!hex) return;
        $wrap.attr('data-hex', hex);
        var $native = $wrap.find('.bt-native');
        if (!$native.is(':focus')) $native.val(hex);
        var $hexIn = $wrap.find('.bt-hex-input');
        if (!$hexIn.is(':focus')) $hexIn.val(hex);
        var rgb = hexToRgb(hex);
        var cmyk = rgbToCmyk(rgb.r, rgb.g, rgb.b);
        var map = { '.bt-c':cmyk.c, '.bt-m':cmyk.m, '.bt-y':cmyk.y, '.bt-k':cmyk.k };
        for (var sel in map) {
            var $f = $wrap.find(sel);
            if (!$f.is(':focus')) $f.val(map[sel]);
        }
    }
    function btColorCommit($wrap){
        if ($wrap.attr('data-target') !== 'batch') return;
        var bid = parseInt($wrap.attr('data-bid'), 10);
        var b = findBatch(bid);
        if (!b) return;
        var hex = btColorGet($wrap);
        if (hex === b.color) return;
        pushUndoSnapshot();
        b.color = hex;
        rerasterizeBatch(b);
    }

    $(document).on('change', '.bt-color .bt-native', function(){
        var $wrap = $(this).closest('.bt-color');
        btColorSet($wrap, this.value);
        btColorCommit($wrap);
    });
    $(document).on('change', '.bt-color .bt-hex-input', function(){
        var $wrap = $(this).closest('.bt-color');
        var v = normHex(this.value);
        if (!v) { btColorSet($wrap, btColorGet($wrap)); return; }
        btColorSet($wrap, v);
        btColorCommit($wrap);
    });
    $(document).on('keydown', '.bt-color .bt-hex-input', function(e){
        if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
    });
    $(document).on('change', '.bt-color .bt-c, .bt-color .bt-m, .bt-color .bt-y, .bt-color .bt-k', function(){
        var $wrap = $(this).closest('.bt-color');
        var rgb = cmykToRgb(
            clamp100($wrap.find('.bt-c').val()),
            clamp100($wrap.find('.bt-m').val()),
            clamp100($wrap.find('.bt-y').val()),
            clamp100($wrap.find('.bt-k').val())
        );
        btColorSet($wrap, rgbToHex(rgb.r, rgb.g, rgb.b));
        btColorCommit($wrap);
    });
    $(document).on('click', '.bt-color .bt-cmode', function(){
        var $wrap = $(this).closest('.bt-color');
        var mode = $(this).data('cmode');
        $wrap.find('.bt-cmode').removeClass('active');
        $(this).addClass('active');
        $wrap.find('.bt-pane-hex').toggle(mode === 'hex');
        $wrap.find('.bt-pane-cmyk').toggle(mode === 'cmyk');
    });

    $('#btgsb-namesnum-color-mount').html(btColorMarkup('#000000', 'main'));

    /* -- Generate mode (Both / Names only / Numbers only) -------- */
    var nnMode = 'both';
    $('#btgsb-namesnum-body').on('click', '.nn-mode-btn', function(){
        var mode = $(this).data('nnmode');
        if (mode === nnMode) return;
        nnMode = mode;
        $('#btgsb-namesnum-body .nn-mode-btn').removeClass('nn-active');
        $(this).addClass('nn-active');
        $('#btgsb-namesnum-body').removeClass('nn-gen-both nn-gen-names nn-gen-numbers').addClass('nn-gen-' + mode);
        $('#btgsb-roster-grid').removeClass('nn-mode-both nn-mode-names nn-mode-numbers').addClass('nn-mode-' + mode);
        var lbl = mode === 'names'   ? 'Roster &mdash; one name per row'
                : mode === 'numbers' ? 'Roster &mdash; one number per row'
                : 'Roster &mdash; name and number per row';
        $('.nn-roster-label').html(lbl);
    });

    /* -- Names & Numbers panel ----------------------------------- */
    $('#btgsb-namesnum-header').on('click', function(){
        $('#btgsb-namesnum-panel').toggleClass('nn-open');
    });

    $('#btgsb-namesnum-body').on('click', '.nn-font-btn', function(){
        $('#btgsb-namesnum-body .nn-font-btn').removeClass('nn-active');
        $(this).addClass('nn-active');
    });

    var $rosterGrid = $('#btgsb-roster-grid');

    function nnAddRow(focusName) {
        var row = $(
            '<div class="nn-row-item">' +
              '<input type="text" class="nn-row-name" placeholder="Name" autocomplete="off">' +
              '<input type="text" class="nn-row-number" placeholder="#" maxlength="4" autocomplete="off">' +
              '<button type="button" class="nn-row-delete" title="Remove this row">\u2715</button>' +
            '</div>'
        );
        $rosterGrid.append(row);
        nnUpdateDeleteState();
        if (focusName) {
            var sel = nnMode === 'numbers' ? '.nn-row-number' : '.nn-row-name';
            row.find(sel).focus();
        }
        return row;
    }

    function nnUpdateDeleteState() {
        var $rows = $rosterGrid.find('.nn-row-item');
        $rows.find('.nn-row-delete').prop('disabled', $rows.length <= 1);
    }

    nnAddRow(false);

    $('#btgsb-roster-add').on('click', function(){
        nnAddRow(true);
    });

    $rosterGrid.on('click', '.nn-row-delete', function(){
        var $row = $(this).closest('.nn-row-item');
        var $rows = $rosterGrid.find('.nn-row-item');
        if ($rows.length <= 1) return;
        $row.remove();
        nnUpdateDeleteState();
    });

    $rosterGrid.on('keydown', '.nn-row-name, .nn-row-number', function(e){
        if (e.key !== 'Tab' || e.shiftKey) return;
        // The field that ends a row (and should spawn the next one on Tab)
        // depends on the mode: in Names-only it's the name field, otherwise
        // it's the number field (which is also the last field in Both mode).
        var triggerIsName = (nnMode === 'names');
        var isName = $(this).hasClass('nn-row-name');
        if (triggerIsName !== isName) return;
        var $row = $(this).closest('.nn-row-item');
        var $rows = $rosterGrid.find('.nn-row-item');
        if ($rows.index($row) === $rows.length - 1) {
            e.preventDefault();
            nnAddRow(true);
        }
    });

    $rosterGrid.on('keydown', '.nn-row-name, .nn-row-number', function(e){
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var $field = $(this);
        var $row = $field.closest('.nn-row-item');
        var $rows = $rosterGrid.find('.nn-row-item');
        var idx = $rows.index($row);
        if (nnMode === 'names' || nnMode === 'numbers') {
            var sel = nnMode === 'names' ? '.nn-row-name' : '.nn-row-number';
            if (idx === $rows.length - 1) { nnAddRow(true); }
            else { $rows.eq(idx + 1).find(sel).focus(); }
            return;
        }
        if ($field.hasClass('nn-row-name')) {
            $row.find('.nn-row-number').focus();
        } else {
            if (idx === $rows.length - 1) { nnAddRow(true); }
            else { $rows.eq(idx + 1).find('.nn-row-name').focus(); }
        }
    });

    function nnReadRoster() {
        var pieces = [];
        $rosterGrid.find('.nn-row-item').each(function(){
            var name = $(this).find('.nn-row-name').val().trim();
            var num  = $(this).find('.nn-row-number').val().trim();
            if (nnMode !== 'numbers') { if (name) pieces.push({ text: name, kind: 'name'   }); }
            if (nnMode !== 'names')   { if (num)  pieces.push({ text: num,  kind: 'number' }); }
        });
        return pieces;
    }

    function nnResetRoster() {
        $rosterGrid.empty();
        nnAddRow(false);
    }

    function renderTextPiece(text, fontKey, heightIn, color) {
        return new Promise(function(resolve, reject){
            try {
                var fontDef  = NN_FONTS[fontKey] || NN_FONTS.varsity;
                var heightPx = Math.max(20, Math.round(heightIn * NN_RENDER_DPI));
                var pad      = 4;
                var fontSpec = fontDef.weight + ' ' + heightPx + 'px ' + fontDef.family;

                // Tight ink bounds straight from font metrics. The old version
                // drew onto a canvas up to ~9600x4800px and scanned EVERY pixel
                // to find the trim box, which froze the tab when generating
                // several large pieces (e.g. a roster of big numbers).
                // measureText gives the same bounds instantly, no pixel scan.
                var mc   = document.createElement('canvas');
                var mctx = mc.getContext('2d');
                mctx.font = fontSpec;
                mctx.textBaseline = 'alphabetic';
                var m = mctx.measureText(text);

                var metricsOk =
                    typeof m.actualBoundingBoxAscent  === 'number' &&
                    typeof m.actualBoundingBoxDescent === 'number' &&
                    typeof m.actualBoundingBoxLeft    === 'number' &&
                    typeof m.actualBoundingBoxRight   === 'number' &&
                    (m.actualBoundingBoxAscent + m.actualBoundingBoxDescent) > 0 &&
                    (m.actualBoundingBoxLeft   + m.actualBoundingBoxRight)   > 0;

               if (metricsOk) {
                    // The font-size was set equal to the target physical height,
                    // but the inked glyph (cap height on these all-caps display
                    // faces) is only ~75% of the em - so an "8 inch" number came
                    // out ~6". Rescale the font so the actual INK height equals
                    // the requested height, then re-measure at the corrected size.
                    var inkH0    = m.actualBoundingBoxAscent + m.actualBoundingBoxDescent;
                    var scaledPx = Math.max(20, Math.round(heightPx * heightPx / inkH0));
                    fontSpec = fontDef.weight + ' ' + scaledPx + 'px ' + fontDef.family;
                    mctx.font = fontSpec;
                    m = mctx.measureText(text);

                    var left   = m.actualBoundingBoxLeft;
                    var ascent = m.actualBoundingBoxAscent;
                    var inkW   = m.actualBoundingBoxLeft   + m.actualBoundingBoxRight;
                    var inkH   = m.actualBoundingBoxAscent + m.actualBoundingBoxDescent;

                    var outCanvas = document.createElement('canvas');
                    outCanvas.width  = Math.ceil(inkW) + pad * 2;
                    outCanvas.height = Math.ceil(inkH) + pad * 2;
                    var octx = outCanvas.getContext('2d');
                    octx.font = fontSpec;
                    octx.textBaseline = 'alphabetic';
                    octx.fillStyle = color;
                    octx.fillText(text, pad + left, pad + ascent);

                    resolve({
                        dataUrl: outCanvas.toDataURL('image/png'),
                        naturalW: outCanvas.width,
                        naturalH: outCanvas.height,
                        widthIn:  outCanvas.width  / NN_RENDER_DPI,
                        heightIn: outCanvas.height / NN_RENDER_DPI
                    });
                    return;
                }

                // Fallback (older browsers without actualBoundingBox*): scan a
                // tightly-sized canvas instead of the giant one.
                var fw = Math.ceil(m.width) + heightPx;
                var fh = Math.ceil(heightPx * 1.6);
                var sc = document.createElement('canvas');
                sc.width = fw; sc.height = fh;
                var sctx = sc.getContext('2d');
                sctx.font = fontSpec;
                sctx.textBaseline = 'alphabetic';
                sctx.fillStyle = color;
                sctx.fillText(text, Math.round(heightPx * 0.5), Math.round(heightPx * 1.2));

                var data = sctx.getImageData(0, 0, fw, fh).data;
                var minX = fw, maxX = -1, minY = fh, maxY = -1;
                for (var y = 0; y < fh; y++) {
                    for (var x = 0; x < fw; x++) {
                        if (data[(y * fw + x) * 4 + 3] !== 0) {
                            if (x < minX) minX = x;
                            if (x > maxX) maxX = x;
                            if (y < minY) minY = y;
                            if (y > maxY) maxY = y;
                        }
                    }
                }
                if (maxX < 0) { reject(new Error('Empty render for "' + text + '"')); return; }

                var trimW = maxX - minX + 1;
                var trimH = maxY - minY + 1;
                var oc = document.createElement('canvas');
                oc.width  = trimW + pad * 2;
                oc.height = trimH + pad * 2;
                oc.getContext('2d').drawImage(sc, minX, minY, trimW, trimH, pad, pad, trimW, trimH);

                resolve({
                    dataUrl: oc.toDataURL('image/png'),
                    naturalW: oc.width,
                    naturalH: oc.height,
                    widthIn:  oc.width  / NN_RENDER_DPI,
                    heightIn: oc.height / NN_RENDER_DPI
                });
            } catch (err) {
                reject(err);
            }
        });
    }

    function nnSetStatus(msg, isError) {
        $('#btgsb-namesnum-status').css('color', isError ? '#c0392b' : '#888').text(msg || '');
    }

    function setStatus(msg, type) {
        var color = type === 'error'   ? '#c0392b'
                  : type === 'success' ? '#27ae60'
                  : '#27267e';
        $('#btgsb-status').css({
            'color': color,
            'font-size': '13px',
            'text-align': 'center',
            'margin-top': '8px',
            'min-height': '16px'
        }).text(msg || '');
    }

    $('#btgsb-namesnum-generate').on('click', function(){
        var $btn = $(this);
        var pieces = nnReadRoster();
        var fontKey = $('.nn-font-btn.nn-active').data('font') || 'varsity';
        var nameHeightIn   = parseFloat($('#btgsb-namesnum-name-height').val())   || 2.5;
        var numberHeightIn = parseFloat($('#btgsb-namesnum-number-height').val()) || 8;
        var color = btColorGet($('#btgsb-namesnum-color-mount .bt-color'));

        if (!pieces.length) {
            nnSetStatus('Enter at least one name or one number.', true);
            return;
        }
        if (nameHeightIn < 0.5 || nameHeightIn > 14 || numberHeightIn < 0.5 || numberHeightIn > 14) {
            nnSetStatus('Heights must be between 0.5 and 14 inches.', true);
            return;
        }

        $btn.prop('disabled', true);
        nnSetStatus('Generating ' + pieces.length + ' piece' + (pieces.length === 1 ? '' : 's') + '\u2026', false);

        var batchId = nextBatchId++;
        var batch = {
            id: batchId,
            name: 'Custom Batch ' + nextBatchNameCounter,
            font: fontKey,
            color: color,
            nameHeight: nameHeightIn,
            numberHeight: numberHeightIn,
            open: true,
            items: []
        };

        var jobs = pieces.map(function(piece){
            var h = piece.kind === 'number' ? numberHeightIn : nameHeightIn;
            return renderTextPiece(piece.text, fontKey, h, color).then(function(res){
                var did = nextId++;
                designs.push({
                    id: did,
                    name: (piece.kind === 'number' ? '#' : '') + piece.text,
                    dataUrl: res.dataUrl,
                    imgEl: null,
                    nW: res.naturalW,
                    nH: res.naturalH,
                    widthIn: res.widthIn,
                    heightIn: res.heightIn,
                    qty: 1,
                    locked: true,
                    rotated: false,
                    rotLock: false,
                    hasAlpha: true,
                    batchId: batchId
                });
                batch.items.push({
                    id: nextBatchItemId++,
                    kind: piece.kind,
                    text: piece.text,
                    designId: did,
                    fontOverride: null
                });
            }).catch(function(err){
                console.error('renderTextPiece failed', err);
            });
        });

        Promise.all(jobs).then(function(){
            if (!batch.items.length) {
                nnSetStatus('Could not render any pieces. Try a different font or text.', true);
                $btn.prop('disabled', false);
                return;
            }
            // Load imgEl for each new design before refreshing
            var loadJobs = designs.filter(function(d){ return d.batchId === batchId; }).map(function(d){
                return new Promise(function(resolve){
                    var img = new Image();
                    img.onload  = function(){ d.imgEl = img; resolve(); };
                    img.onerror = function(){ resolve(); };
                    img.src = d.dataUrl;
                });
            });
            Promise.all(loadJobs).then(function(){
                pushUndoSnapshot();
                batches.push(batch);
                nextBatchNameCounter++;
                refresh();
                nnResetRoster();
                $btn.prop('disabled', false);
                nnSetStatus('Added ' + batch.items.length + ' piece' + (batch.items.length === 1 ? '' : 's') + ' to your sheet.', false);
            });
        });
    });

    /* -- Batch management ---------------------------------------- */
    function findBatch(id) {
        for (var i = 0; i < batches.length; i++) if (batches[i].id === id) return batches[i];
        return null;
    }
    function findBatchItem(batch, itemId) {
        for (var i = 0; i < batch.items.length; i++) if (batch.items[i].id === itemId) return batch.items[i];
        return null;
    }
    function findDesign(id) {
        for (var i = 0; i < designs.length; i++) if (designs[i].id === id) return designs[i];
        return null;
    }

    // Re-render a single batch item's design from its current text/font/height/color.
    function rerasterizeItem(batch, item) {
        var d = findDesign(item.designId);
        if (!d) return Promise.resolve();
        var fontKey = item.fontOverride || batch.font;
        var h = item.kind === 'number' ? batch.numberHeight : batch.nameHeight;
        return renderTextPiece(item.text, fontKey, h, batch.color).then(function(res){
            d.dataUrl  = res.dataUrl;
            d.nW       = res.naturalW;
            d.nH       = res.naturalH;
            // renderTextPiece always returns UNROTATED dimensions. If the
            // customer had turned this piece, re-apply that turn to the fresh
            // raster instead of leaving rotated=true against unrotated dims,
            // which would have stretched the art in its box.
            if (d.rotated) {
                d.widthIn  = res.heightIn;
                d.heightIn = res.widthIn;
            } else {
                d.widthIn  = res.widthIn;
                d.heightIn = res.heightIn;
            }
            d.name     = (item.kind === 'number' ? '#' : '') + item.text;
            return new Promise(function(resolve){
                var img = new Image();
                img.onload  = function(){ d.imgEl = img; resolve(); };
                img.onerror = function(){ resolve(); };
                img.src = d.dataUrl;
            });
        }).catch(function(err){ console.error('rerasterizeItem failed', err); });
    }

    // Re-render every item in a batch (font/color/height change).
    function rerasterizeBatch(batch) {
        nnSetStatus('Updating ' + batch.name + '\u2026', false);
        var jobs = batch.items.map(function(item){ return rerasterizeItem(batch, item); });
        Promise.all(jobs).then(function(){
            refresh();
            nnSetStatus('', false);
        });
    }

    function renderBatchCards() {
        var $list = $('#btgsb-batch-list');
        $list.empty();

        batches.forEach(function(b){
            var $card = $('<div class="bb-card' + (b.open ? ' bb-open' : '') + '" data-bid="' + b.id + '"></div>');

            var $head = $(
                '<div class="bb-card-head">' +
                  '<input class="bb-card-title" value="' + esc(b.name) + '" spellcheck="false">' +
                  '<span class="bb-card-count">' + b.items.length + ' pcs</span>' +
                  '<button type="button" class="bb-card-x" title="Delete batch">\u2715</button>' +
                  '<span class="bb-card-caret">\u25B6</span>' +
                '</div>'
            );
            $card.append($head);

            var $body = $('<div class="bb-card-body"></div>');

            // Font picker
            var $fontWrap = $('<div></div>');
            $fontWrap.append('<label class="nn-label">Batch font</label>');
            var $fontGrid = $('<div class="nn-font-grid"></div>');
            Object.keys(NN_FONTS).forEach(function(key){
                var fd = NN_FONTS[key];
                var active = (b.font === key) ? ' nn-active' : '';
                var sampleStyle = 'font-family:' + fd.family + ';font-weight:' + fd.weight;
                $fontGrid.append(
                    '<button type="button" class="nn-font-btn' + active + '" data-font="' + key + '">' +
                      '<span class="nn-font-sample" style="' + sampleStyle + '">Aa</span>' +
                      '<span class="nn-font-name">' + fd.label + '</span>' +
                    '</button>'
                );
            });
            $fontWrap.append($fontGrid);
            $body.append($fontWrap);

            // Heights
            $body.append(
                '<div class="nn-row">' +
                  '<div><label class="nn-label">Name height (in)</label>' +
                    '<input type="number" class="bb-name-h" step="0.25" min="0.5" max="14" value="' + b.nameHeight + '"></div>' +
                  '<div><label class="nn-label">Number height (in)</label>' +
                    '<input type="number" class="bb-number-h" step="0.25" min="0.5" max="14" value="' + b.numberHeight + '"></div>' +
                '</div>'
            );

            // Color (Hex / CMYK control)
            $body.append('<div><label class="nn-label">Color</label>' + btColorMarkup(b.color, 'batch', b.id) + '</div>');

            // Items
            var $items = $('<div class="bb-items"></div>');
            b.items.forEach(function(item){
                var kindCls = item.kind === 'number' ? ' k-number' : '';
                var fontLbl = item.fontOverride ? (NN_FONTS[item.fontOverride] || {}).label || 'Font' : 'Font';
                var overrideCls = item.fontOverride ? ' bb-has-override' : '';
                $items.append(
                    '<div class="bb-item" data-iid="' + item.id + '">' +
                      '<span class="bb-item-kind' + kindCls + '">' + (item.kind === 'number' ? 'No.' : 'Name') + '</span>' +
                      '<input class="bb-item-text" value="' + esc(item.text) + '" spellcheck="false">' +
                      '<button type="button" class="bb-item-fontbtn' + overrideCls + '" title="Per-piece font">' + fontLbl + '</button>' +
                      '<button type="button" class="bb-item-x" title="Remove piece">\u2715</button>' +
                    '</div>'
                );
            });
            $body.append($items);

            // Add piece row
            $body.append(
                '<div class="bb-add-row">' +
                  '<input class="bb-add-name" placeholder="Name" autocomplete="off">' +
                  '<input class="bb-add-num" placeholder="#" maxlength="4" autocomplete="off">' +
                  '<button type="button" class="bb-add-btn">+ Add</button>' +
                '</div>'
            );

            $card.append($body);
            $list.append($card);
        });
    }

    // Toggle batch open/closed
    $('#btgsb-batch-list').on('click', '.bb-card-head', function(e){
        if ($(e.target).is('input, button')) return;
        var bid = $(this).closest('.bb-card').data('bid');
        var b = findBatch(bid);
        if (!b) return;
        b.open = !b.open;
        $(this).closest('.bb-card').toggleClass('bb-open', b.open);
    });

    // Rename batch
    $('#btgsb-batch-list').on('change', '.bb-card-title', function(){
        var bid = $(this).closest('.bb-card').data('bid');
        var b = findBatch(bid);
        if (!b) return;
        b.name = this.value.trim() || b.name;
        this.value = b.name;
        autosaveLocal();
    });

    // Delete batch
    $('#btgsb-batch-list').on('click', '.bb-card-x', function(){
        var bid = $(this).closest('.bb-card').data('bid');
        var b = findBatch(bid);
        if (!b) return;
        if (!confirm('Delete "' + b.name + '" and all its pieces?\n\nUndo with Ctrl+Z if needed.')) return;
        pushUndoSnapshot();
        b.items.forEach(function(item){
            var idx = designs.findIndex(function(d){ return d.id === item.designId; });
            if (idx !== -1) designs.splice(idx, 1);
        });
        var bidx = batches.findIndex(function(x){ return x.id === bid; });
        if (bidx !== -1) batches.splice(bidx, 1);
        refresh();
    });

    // Batch font change
    $('#btgsb-batch-list').on('click', '.bb-card-body > div .nn-font-btn', function(){
        var $card = $(this).closest('.bb-card');
        var bid = $card.data('bid');
        var b = findBatch(bid);
        if (!b) return;
        var key = $(this).data('font');
        if (key === b.font) return;
        pushUndoSnapshot();
        b.font = key;
        $card.find('.bb-card-body > div .nn-font-btn').removeClass('nn-active');
        $(this).addClass('nn-active');
        rerasterizeBatch(b);
    });

    // Batch height change
    $('#btgsb-batch-list').on('change', '.bb-name-h', function(){
        var bid = $(this).closest('.bb-card').data('bid');
        var b = findBatch(bid);
        if (!b) return;
        var v = parseFloat(this.value);
        if (isNaN(v) || v < 0.5 || v > 14) { this.value = b.nameHeight; return; }
        if (v === b.nameHeight) return;
        pushUndoSnapshot();
        b.nameHeight = v;
        rerasterizeBatch(b);
    });
    $('#btgsb-batch-list').on('change', '.bb-number-h', function(){
        var bid = $(this).closest('.bb-card').data('bid');
        var b = findBatch(bid);
        if (!b) return;
        var v = parseFloat(this.value);
        if (isNaN(v) || v < 0.5 || v > 14) { this.value = b.numberHeight; return; }
        if (v === b.numberHeight) return;
        pushUndoSnapshot();
        b.numberHeight = v;
        rerasterizeBatch(b);
    });

    // Edit piece text
    $('#btgsb-batch-list').on('change', '.bb-item-text', function(){
        var $card = $(this).closest('.bb-card');
        var bid = $card.data('bid');
        var iid = $(this).closest('.bb-item').data('iid');
        var b = findBatch(bid);
        if (!b) return;
        var item = findBatchItem(b, iid);
        if (!item) return;
        var v = this.value.trim();
        if (!v || v === item.text) { this.value = item.text; return; }
        pushUndoSnapshot();
        item.text = v;
        rerasterizeItem(b, item).then(function(){ refresh(); });
    });

    // Delete piece
    $('#btgsb-batch-list').on('click', '.bb-item-x', function(){
        var $card = $(this).closest('.bb-card');
        var bid = $card.data('bid');
        var iid = $(this).closest('.bb-item').data('iid');
        var b = findBatch(bid);
        if (!b) return;
        var item = findBatchItem(b, iid);
        if (!item) return;
        pushUndoSnapshot();
        var didx = designs.findIndex(function(d){ return d.id === item.designId; });
        if (didx !== -1) designs.splice(didx, 1);
        var iidx = b.items.findIndex(function(x){ return x.id === iid; });
        if (iidx !== -1) b.items.splice(iidx, 1);
        if (!b.items.length) {
            var bidx = batches.findIndex(function(x){ return x.id === bid; });
            if (bidx !== -1) batches.splice(bidx, 1);
        }
        refresh();
    });

    // Add piece to existing batch
    $('#btgsb-batch-list').on('click', '.bb-add-btn', function(){
        var $card = $(this).closest('.bb-card');
        var bid = $card.data('bid');
        var b = findBatch(bid);
        if (!b) return;
        var $row = $(this).closest('.bb-add-row');
        var name = $row.find('.bb-add-name').val().trim();
        var num  = $row.find('.bb-add-num').val().trim();
        var newPieces = [];
        if (name) newPieces.push({ text: name, kind: 'name' });
        if (num)  newPieces.push({ text: num,  kind: 'number' });
        if (!newPieces.length) return;

        pushUndoSnapshot();
        var jobs = newPieces.map(function(piece){
            var fontKey = b.font;
            var h = piece.kind === 'number' ? b.numberHeight : b.nameHeight;
            return renderTextPiece(piece.text, fontKey, h, b.color).then(function(res){
                var did = nextId++;
                designs.push({
                    id: did,
                    name: (piece.kind === 'number' ? '#' : '') + piece.text,
                    dataUrl: res.dataUrl, imgEl: null,
                    nW: res.naturalW, nH: res.naturalH,
                    widthIn: res.widthIn, heightIn: res.heightIn,
                    qty: 1, locked: true, rotated: false, rotLock: false, hasAlpha: true,
                    batchId: bid
                });
                var item = { id: nextBatchItemId++, kind: piece.kind, text: piece.text, designId: did, fontOverride: null };
                b.items.push(item);
                return new Promise(function(resolve){
                    var img = new Image();
                    img.onload  = function(){ findDesign(did).imgEl = img; resolve(); };
                    img.onerror = function(){ resolve(); };
                    img.src = res.dataUrl;
                });
            }).catch(function(err){ console.error('add piece failed', err); });
        });
        Promise.all(jobs).then(function(){
            $row.find('.bb-add-name').val('');
            $row.find('.bb-add-num').val('');
            refresh();
        });
    });

    // Per-piece font override popover
    $('#btgsb-batch-list').on('click', '.bb-item-fontbtn', function(e){
        e.stopPropagation();
        $('.bb-popover').remove();
        var $btn = $(this);
        var $card = $btn.closest('.bb-card');
        var bid = $card.data('bid');
        var iid = $btn.closest('.bb-item').data('iid');
        var b = findBatch(bid);
        if (!b) return;
        var item = findBatchItem(b, iid);
        if (!item) return;

        var $pop = $('<div class="bb-popover"></div>');
        Object.keys(NN_FONTS).forEach(function(key){
            var fd = NN_FONTS[key];
            var active = (item.fontOverride === key) ? ' nn-active' : '';
            var sampleStyle = 'font-family:' + fd.family + ';font-weight:' + fd.weight;
            $pop.append(
                '<button type="button" class="nn-font-btn' + active + '" data-font="' + key + '">' +
                  '<span class="nn-font-sample" style="' + sampleStyle + '">Aa</span>' +
                  '<span class="nn-font-name">' + fd.label + '</span>' +
                '</button>'
            );
        });
        $pop.append('<button type="button" class="bb-pop-clear">Use batch font</button>');

        $('body').append($pop);
        var off = $btn.offset();
        var popW = $pop.outerWidth();
        var left = off.left;
        if (left + popW > $(window).width() - 10) left = $(window).width() - popW - 10;
        $pop.css({ top: off.top + $btn.outerHeight() + 4, left: Math.max(10, left) });

        $pop.on('click', '.nn-font-btn', function(){
            pushUndoSnapshot();
            item.fontOverride = $(this).data('font');
            $pop.remove();
            rerasterizeItem(b, item).then(function(){ refresh(); });
        });
        $pop.on('click', '.bb-pop-clear', function(){
            if (item.fontOverride) {
                pushUndoSnapshot();
                item.fontOverride = null;
                rerasterizeItem(b, item).then(function(){ refresh(); });
            }
            $pop.remove();
        });
    });

    $(document).on('click', function(e){
        if (!$(e.target).closest('.bb-popover, .bb-item-fontbtn').length) $('.bb-popover').remove();
    });

    /* -- Undo system --------------------------------------------- */
    function snapshotState() {
        // Return a plain object, NOT a JSON string. The previous version
        // JSON.stringify'd every design including its full base64 dataUrl on
        // every mutation, materializing a fresh multi-MB string each time and
        // blowing up memory. Here the dataUrl strings are shared by reference
        // (strings are immutable in JS, so this costs a pointer, not a copy);
        // only the lightweight metadata is duplicated.
        return {
            designs: designs.map(function(d){
                return {
                    id:d.id, name:d.name, dataUrl:d.dataUrl, nW:d.nW, nH:d.nH,
                    widthIn:d.widthIn, heightIn:d.heightIn, qty:d.qty,
                    locked:d.locked, rotated:d.rotated, rotLock:d.rotLock, hasAlpha:d.hasAlpha,
                    batchId: d.batchId || null
                };
            }),
            batches: batches.map(function(b){
                return {
                    id:b.id, name:b.name, font:b.font, color:b.color,
                    nameHeight:b.nameHeight, numberHeight:b.numberHeight, open:b.open,
                    items: b.items.map(function(it){
                        return { id:it.id, kind:it.kind, text:it.text, designId:it.designId, fontOverride:it.fontOverride };
                    })
                };
            }),
            anchors: sheetState.placements.filter(function(p){ return p.anchored; }).map(function(p){
                return { designId:p.designId, x:p.x, y:p.y };
            }),
            counters: {
                nextId:nextId, nextBatchId:nextBatchId,
                nextBatchItemId:nextBatchItemId, nextBatchNameCounter:nextBatchNameCounter
            }
        };
    }

    function pushUndoSnapshot() {
        if (isRestoring) return;
        undoStack.push(snapshotState());
        if (undoStack.length > UNDO_LIMIT) undoStack.shift();
    }

    function restoreSnapshot(snap) {
        if (!snap || !snap.designs) return;
        isRestoring = true;

        nextId               = snap.counters.nextId;
        nextBatchId          = snap.counters.nextBatchId;
        nextBatchItemId      = snap.counters.nextBatchItemId;
        nextBatchNameCounter = snap.counters.nextBatchNameCounter;

        batches = snap.batches.map(function(b){
            return {
                id:b.id, name:b.name, font:b.font, color:b.color,
                nameHeight:b.nameHeight, numberHeight:b.numberHeight, open:b.open,
                items: b.items.map(function(it){
                    return { id:it.id, kind:it.kind, text:it.text, designId:it.designId, fontOverride:it.fontOverride };
                })
            };
        });

        designs = snap.designs.map(function(d){
            return {
                id:d.id, name:d.name, dataUrl:d.dataUrl, imgEl:null,
                nW:d.nW, nH:d.nH, widthIn:d.widthIn, heightIn:d.heightIn,
                qty:d.qty, locked:d.locked, rotated:d.rotated, rotLock:!!d.rotLock, hasAlpha:d.hasAlpha,
                batchId: d.batchId || null
            };
        });

        selectedPlacement = null;
        sheetState.placements = [];

        var loadJobs = designs.map(function(d){
            return new Promise(function(resolve){
                var img = new Image();
                img.onload  = function(){ d.imgEl = img; resolve(); };
                img.onerror = function(){ resolve(); };
                img.src = d.dataUrl;
            });
        });

        Promise.all(loadJobs).then(function(){
            recalculate();
            // Re-apply anchored positions
            (snap.anchors || []).forEach(function(a){
                for (var i=0;i<sheetState.placements.length;i++){
                    var p = sheetState.placements[i];
                    if (p.designId === a.designId && !p.anchored) {
                        p.x = a.x; p.y = a.y; p.anchored = true;
                        break;
                    }
                }
            });
            isRestoring = false;
            refresh();
        });
    }

    function undo() {
        if (!undoStack.length) return;
        var snap = undoStack.pop();
        restoreSnapshot(snap);
    }

    function updateUndoButton() {
        var $b = $('#btgsb-undo-btn');
        if (undoStack.length > 0) {
            $b.css('display', 'inline-flex').prop('disabled', false);
        } else {
            $b.css('display', 'none');
        }
    }

    $('#btgsb-undo-btn').on('click', function(){ undo(); });

    $(document).on('keydown', function(e){
        if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z') && !e.shiftKey) {
            var tag = (e.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea') return;
            e.preventDefault();
            undo();
        }
    });

    /* -- Selection inspector ------------------------------------- */
    function updateInspector() {
        var $insp = $('#btgsb-inspector');
        if (!selectedPlacement) { $insp.removeClass('bi-show'); return; }
        var d = findDesign(selectedPlacement.designId);
        if (!d) { $insp.removeClass('bi-show'); return; }
        $insp.addClass('bi-show');
        $('#btgsb-inspector-name').text(d.name);
        $('#btgsb-inspector-w').val(selectedPlacement.w.toFixed(2));
        $('#btgsb-inspector-h').val(selectedPlacement.h.toFixed(2));
        var locked = (d.locked !== false);
        $('#btgsb-inspector-lock').toggleClass('bi-locked', locked)
            .html(locked ? '&#x1F512;' : '&#x1F513;');
    }

    function applyInspectorSize(which) {
        if (!selectedPlacement) return;
        var d = findDesign(selectedPlacement.designId);
        if (!d) return;
        var w = parseFloat($('#btgsb-inspector-w').val());
        var h = parseFloat($('#btgsb-inspector-h').val());
        if (which === 'w' && (isNaN(w) || w < 0.25)) return;
        if (which === 'h' && (isNaN(h) || h < 0.25)) return;
        pushUndoSnapshot();
        if (d.locked !== false) {
            if (which === 'w') {
                d.widthIn = Math.min(w, SHEET_W);
                var hperw = d.rotated ? (d.nW / d.nH) : (d.nH / d.nW);
                d.heightIn = parseFloat((d.widthIn * hperw).toFixed(2));
            } else {
                d.heightIn = h;
                var wperh = d.rotated ? (d.nH / d.nW) : (d.nW / d.nH);
                d.widthIn = Math.min(parseFloat((d.heightIn * wperh).toFixed(2)), SHEET_W);
            }
        } else {
            if (!isNaN(w)) d.widthIn = Math.min(w, SHEET_W);
            if (!isNaN(h)) d.heightIn = h;
        }
        refresh();
    }

    $('#btgsb-inspector-w').on('change', function(){ applyInspectorSize('w'); });
    $('#btgsb-inspector-h').on('change', function(){ applyInspectorSize('h'); });
    $('#btgsb-inspector-lock').on('click', function(){
        if (!selectedPlacement) return;
        var d = findDesign(selectedPlacement.designId);
        if (!d) return;
        d.locked = (d.locked === false) ? true : false;
        refresh();
    });

    /* -- Canvas interactions (move / resize) --------------------- */
    function getMouseInches(e) {
        var rect = previewCanvas.getBoundingClientRect();
        var scaleX = previewCanvas.width  / rect.width;
        var scaleY = previewCanvas.height / rect.height;
        var px = (e.clientX - rect.left) * scaleX - RULER_PX;
        var py = (e.clientY - rect.top)  * scaleY - RULER_PX;
        var dpi = PX_PER_IN * zoom;
        return { x: px / dpi, y: py / dpi };
    }

    function hitTest(ix, iy) {
        for (var i = sheetState.placements.length - 1; i >= 0; i--) {
            var p = sheetState.placements[i];
            if (ix >= p.x && ix <= p.x + p.w && iy >= p.y && iy <= p.y + p.h) return p;
        }
        return null;
    }

    function getZone(p, ix, iy) {
        var e = RESIZE_EDGE_IN;
        var left   = ix <= p.x + e;
        var right  = ix >= p.x + p.w - e;
        var top    = iy <= p.y + e;
        var bottom = iy >= p.y + p.h - e;
        if (top && left)     return 'nw';
        if (top && right)    return 'ne';
        if (bottom && left)  return 'sw';
        if (bottom && right) return 'se';
        if (top)    return 'n';
        if (bottom) return 's';
        if (left)   return 'w';
        if (right)  return 'e';
        return 'move';
    }

    function cursorForZone(zone) {
        switch (zone) {
            case 'nw': case 'se': return 'nwse-resize';
            case 'ne': case 'sw': return 'nesw-resize';
            case 'n':  case 's':  return 'ns-resize';
            case 'e':  case 'w':  return 'ew-resize';
            default: return 'move';
        }
    }

    function showTooltip(text, clientX, clientY) {
        var wrap = document.getElementById('btgsb-canvas-wrap');
        var wrapRect = wrap.getBoundingClientRect();
        $('#btgsb-drag-tooltip').css({
            display: 'block',
            left: (clientX - wrapRect.left + wrap.scrollLeft + 12) + 'px',
            top:  (clientY - wrapRect.top  + wrap.scrollTop  - 28) + 'px'
        }).text(text);
    }
    function hideTooltip() { $('#btgsb-drag-tooltip').css('display', 'none'); }

    previewCanvas.addEventListener('mousedown', function(e){
        var m = getMouseInches(e);
        if (m.x < 0 || m.y < 0) return;
        var p = hitTest(m.x, m.y);
        if (!p) { selectedPlacement = null; refresh(); return; }
        selectedPlacement = p;
        // Select WITHOUT pinning. Pinning used to happen right here on any
        // mousedown, so merely tapping a piece froze it at its current spot
        // forever - the root of stale-pin overlaps. The pin (and the undo
        // snapshot) now happen in mousemove, on the first real movement.
        var zone = getZone(p, m.x, m.y);
        dragState = {
            placement: p,
            zone: zone,
            startX: m.x, startY: m.y,
            origX: p.x, origY: p.y, origW: p.w, origH: p.h,
            moved: false
        };
        e.preventDefault();
        // Redraw directly instead of refresh(): reconcile would rebuild free
        // placements as new objects and orphan dragState.placement mid-drag.
        renderSheet(previewCtx, previewCanvas, sheetState.placements, sheetState.sheetH, PX_PER_IN * zoom, true, true);
        updateInspector();
    });

    window.addEventListener('mousemove', function(e){
        if (!dragState) {
            return;
        }
        var m = getMouseInches(e);
        var p = dragState.placement;
        var d = findDesign(p.designId);
        var dx = m.x - dragState.startX;
        var dy = m.y - dragState.startY;

        if (!dragState.moved) {
            // Ignore the couple-pixel wobble of a plain click so selecting a
            // piece never pins or resizes it.
            if (Math.abs(dx) < 0.05 && Math.abs(dy) < 0.05) return;
            dragState.moved = true;
            pushUndoSnapshot();
        }

        if (dragState.zone === 'move') {
            p.x = Math.max(0, Math.min(dragState.origX + dx, SHEET_W - p.w));
            p.y = Math.max(0, dragState.origY + dy);
            p.anchored = true;
           showTooltip('X ' + p.x.toFixed(2) + '"  Y ' + p.y.toFixed(2) + '"', e.clientX, e.clientY);
        } else {
            var locked = d && (d.locked !== false);
            var z = dragState.zone;
            var newW = dragState.origW, newH = dragState.origH, newX = dragState.origX, newY = dragState.origY;
            if (z.indexOf('e') !== -1) newW = Math.max(0.25, dragState.origW + dx);
            if (z.indexOf('w') !== -1) { newW = Math.max(0.25, dragState.origW - dx); newX = dragState.origX + (dragState.origW - newW); }
            if (z.indexOf('s') !== -1) newH = Math.max(0.25, dragState.origH + dy);
            if (z.indexOf('n') !== -1) { newH = Math.max(0.25, dragState.origH - dy); newY = dragState.origY + (dragState.origH - newH); }

            if (locked && (z === 'nw' || z === 'ne' || z === 'sw' || z === 'se') && d) {
                var ar = dragState.origW / dragState.origH;
                newH = newW / ar;
                if (z === 'nw' || z === 'ne') newY = dragState.origY + (dragState.origH - newH);
                if (z === 'nw' || z === 'sw') newX = dragState.origX + (dragState.origW - newW);
            }

            newW = Math.min(newW, SHEET_W);
            if (d) {
                if (!!p.rotated === !!d.rotated) {
                    d.widthIn  = parseFloat(newW.toFixed(2));
                    d.heightIn = parseFloat(newH.toFixed(2));
                } else {
                    d.widthIn  = parseFloat(newH.toFixed(2));
                    d.heightIn = parseFloat(newW.toFixed(2));
                }
            }
			p.w = newW;
            p.h = newH;
            p.x = Math.max(0, newX);
            p.y = Math.max(0, newY);
            p.anchored = true;
            showTooltip(newW.toFixed(2) + '" \u00D7 ' + newH.toFixed(2) + '"', e.clientX, e.clientY);
        }

        // Live redraw without full reconcile (anchored keeps position)
        renderSheet(previewCtx, previewCanvas, sheetState.placements, computeSheetH(sheetState.placements), PX_PER_IN * zoom, true, true);
    });

    window.addEventListener('mouseup', function(){
        if (!dragState) return;
        dragState = null;
        hideTooltip();
        refresh();
    });

    previewCanvas.addEventListener('mousemove', function(e){
        if (dragState) return;
        var m = getMouseInches(e);
        if (m.x < 0 || m.y < 0) { previewCanvas.style.cursor = 'default'; return; }
        var p = hitTest(m.x, m.y);
        if (!p) { previewCanvas.style.cursor = 'default'; return; }
        if (selectedPlacement === p) {
            previewCanvas.style.cursor = cursorForZone(getZone(p, m.x, m.y));
        } else {
            previewCanvas.style.cursor = 'pointer';
        }
    });

    $(document).on('keydown', function(e){
        if (e.key === 'Escape' && selectedPlacement) {
            selectedPlacement = null;
            refresh();
        }
    });

    /* -- Auto Layout (clear all pins) ---------------------------- */
    $('#btgsb-auto-layout-btn').on('click', function(){
        if (!countAnchored()) return;
        pushUndoSnapshot();
        sheetState.placements.forEach(function(p){ p.anchored = false; });
        selectedPlacement = null;
        refresh();
    });

    /* -- External libs (lazy-loaded) ----------------------------- */
    var _pdfjsPromise = null;
    function ensurePdfJs() {
        if (window.pdfjsLib) return Promise.resolve(window.pdfjsLib);
        if (_pdfjsPromise) return _pdfjsPromise;
        _pdfjsPromise = new Promise(function(resolve, reject){
            var s = document.createElement('script');
            s.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
            s.onload = function(){
                try {
                    window.pdfjsLib.GlobalWorkerOptions.workerSrc =
                        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                } catch(e){}
                resolve(window.pdfjsLib);
            };
            s.onerror = function(){ reject(new Error('Could not load PDF reader.')); };
            document.head.appendChild(s);
        });
        return _pdfjsPromise;
    }

    /* -- Load a single file (PNG or PDF) ------------------------- */
    function addDesignFromImage(name, dataUrl, img, hasAlpha) {
        var did = nextId++;
        var nW = img.naturalWidth;
        var nH = img.naturalHeight;
        // Default print size: fit width to a sensible default, capped to sheet width
        var defaultW = Math.min(SHEET_W, Math.max(3, Math.min(11, nW / 150)));
        var heightIn = parseFloat((defaultW * (nH / nW)).toFixed(2));
        designs.push({
            id: did, name: name, dataUrl: dataUrl, imgEl: img,
            nW: nW, nH: nH,
            widthIn: parseFloat(defaultW.toFixed(2)), heightIn: heightIn,
            qty: 1, locked: true, rotated: false, rotLock: false,
            hasAlpha: hasAlpha, batchId: null
        });
    }

    function loadPngFile(file) {
        return new Promise(function(resolve, reject){
            var reader = new FileReader();
            reader.onload = function(){
                var img = new Image();
                img.onload = function(){
                    var hasAlpha = imageHasTransparency(img);
                    addDesignFromImage(file.name.replace(/\.[^.]+$/, ''), reader.result, img, hasAlpha);
                    resolve();
                };
                img.onerror = function(){ reject(new Error('Could not read ' + file.name)); };
                img.src = reader.result;
            };
            reader.onerror = function(){ reject(new Error('Could not read ' + file.name)); };
            reader.readAsDataURL(file);
        });
    }

    function loadPdfFile(file) {
        return ensurePdfJs().then(function(pdfjsLib){
            return new Promise(function(resolve, reject){
                var reader = new FileReader();
                reader.onload = function(){
                    var typed = new Uint8Array(reader.result);
                    pdfjsLib.getDocument({ data: typed }).promise.then(function(pdf){
                        var pageJobs = [];
                        for (var pn = 1; pn <= pdf.numPages; pn++) {
                            (function(pageNum){
                                pageJobs.push(
                                    pdf.getPage(pageNum).then(function(page){
                                        var targetDpi = 300;
                                        var viewport = page.getViewport({ scale: targetDpi / 72 });
                                        var canvas = document.createElement('canvas');
                                        canvas.width  = Math.round(viewport.width);
                                        canvas.height = Math.round(viewport.height);
                                        var ctx = canvas.getContext('2d');
                                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                                        return page.render({
                                            canvasContext: ctx,
                                            viewport: viewport,
                                            background: 'rgba(0,0,0,0)'
                                        }).promise.then(function(){
											
											
                                            var hasAlpha = canvasHasTransparentPixels(canvas);
                                            var dataUrl = canvas.toDataURL('image/png');
                                            return new Promise(function(res){
                                                var img = new Image();
                                                img.onload = function(){
                                                    var baseName = file.name.replace(/\.[^.]+$/, '');
                                                    var name = pdf.numPages > 1 ? (baseName + ' p' + pageNum) : baseName;
                                                    addDesignFromImage(name, dataUrl, img, hasAlpha);
                                                    res();
                                                };
                                                img.onerror = function(){ res(); };
                                                img.src = dataUrl;
                                            });
                                        });
                                    })
                                );
                            })(pn);
                        }
                        Promise.all(pageJobs).then(resolve).catch(reject);
                    }).catch(function(){ reject(new Error('Could not parse ' + file.name)); });
                };
                reader.onerror = function(){ reject(new Error('Could not read ' + file.name)); };
                reader.readAsArrayBuffer(file);
            });
        });
    }

    function loadFile(file) {
        var nameLc = (file.name || '').toLowerCase();
        var isPng = file.type === 'image/png' || /\.png$/.test(nameLc);
        var isPdf = file.type === 'application/pdf' || /\.pdf$/.test(nameLc);
        if (/\.(jpe?g)$/.test(nameLc) || file.type === 'image/jpeg') {
            return Promise.reject(new Error('JPG files are not accepted \u2014 DTF needs a transparent background. Please upload a PNG or PDF.'));
        }
        if (isPng) return loadPngFile(file);
        if (isPdf) return loadPdfFile(file);
        return Promise.reject(new Error('Unsupported file type: ' + file.name + ' (use PNG or PDF)'));
    }

    function handleFiles(fileList) {
        var files = Array.prototype.slice.call(fileList);
        if (!files.length) return;
        setStatus('Loading ' + files.length + ' file' + (files.length === 1 ? '' : 's') + '\u2026', 'info');
        pushUndoSnapshot();
        var jobs = files.map(function(f){
            return loadFile(f).catch(function(err){
                setStatus(err.message || ('Could not load ' + f.name), 'error');
                return null;
            });
        });
        Promise.all(jobs).then(function(){
            refresh();
            setStatus('', 'info');
        });
    }

    $('#btgsb-file-input').on('change', function(){
        handleFiles(this.files);
        this.value = '';
    });

    var $uz = $('#btgsb-upload-zone');
    $uz.on('dragover', function(e){
        e.preventDefault(); e.stopPropagation();
        $uz.addClass('btgsb-drag-over');
    });
    $uz.on('dragleave', function(e){
        e.preventDefault(); e.stopPropagation();
        $uz.removeClass('btgsb-drag-over');
    });
    $uz.on('drop', function(e){
        e.preventDefault(); e.stopPropagation();
        $uz.removeClass('btgsb-drag-over');
        var dt = e.originalEvent.dataTransfer;
        if (dt && dt.files && dt.files.length) handleFiles(dt.files);
    });
    $uz.on('keydown', function(e){
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $('#btgsb-file-input').trigger('click');
        }
    });

    /* -- Zoom ---------------------------------------------------- */
    $('#btgsb-zoom-in').on('click', function(){
        zoom = Math.min(1.5, zoom + 0.15);
        refresh();
    });
    $('#btgsb-zoom-out').on('click', function(){
        zoom = Math.max(0.25, zoom - 0.15);
        refresh();
    });

    /* -- Production-file helpers --------------------------------- */
    function btgsbSafeName(s) {
        var out = String(s == null ? 'design' : s)
            .replace(/[^A-Za-z0-9._-]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .slice(0, 60);
        return out || 'design';
    }

    function btgsbDataUrlToBlob(dataUrl) {
        var parts = dataUrl.split(',');
        var mime  = (parts[0].match(/:(.*?);/) || [null, 'image/png'])[1];
        var bin   = atob(parts[1]);
        var len   = bin.length;
        var arr   = new Uint8Array(len);
        for (var i = 0; i < len; i++) arr[i] = bin.charCodeAt(i);
        return new Blob([arr], { type: mime });
    }

    var _jszipPromise = null;
    function ensureJsZip() {
        if (window.JSZip) return Promise.resolve(window.JSZip);
        if (_jszipPromise) return _jszipPromise;
        _jszipPromise = new Promise(function(resolve, reject){
            var s = document.createElement('script');
            s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js';
            s.onload  = function(){ window.JSZip ? resolve(window.JSZip) : reject(new Error('ZIP library failed to initialize.')); };
            s.onerror = function(){ reject(new Error('Could not load the ZIP library.')); };
            document.head.appendChild(s);
        });
        return _jszipPromise;
    }

    // Build a ZIP of the individual design PNGs (one file per design, named
    // with its copy count) plus a manifest array matching what the backend
    // expects: { file, design, original, w, h, qty }. Each PNG is stamped
    // with its own physical-size DPI so it opens at the right print size.
    function buildProductionZip() {
        return ensureJsZip().then(function(JSZip){
            var zip = new JSZip();
            var manifest = [];
            var used = {};
            var jobs = designs.map(function(d, i){
                var qty  = Math.max(1, parseInt(d.qty, 10) || 1);
                var base = btgsbSafeName(d.name);
                var fname = ('0' + (i + 1)).slice(-2) + '_' + base + '_x' + qty + '.png';
                if (used[fname]) fname = ('0' + (i + 1)).slice(-2) + '_' + base + '_' + i + '_x' + qty + '.png';
                used[fname] = true;
                manifest.push({
                    file: fname, design: d.name, original: d.name,
                    w: d.widthIn.toFixed(2), h: d.heightIn.toFixed(2), qty: qty
                });
                var dpi  = Math.max(1, Math.round(d.nW / Math.max(0.01, d.widthIn)));
                var blob = btgsbDataUrlToBlob(d.dataUrl);
                return injectPngDpi(blob, dpi)
                    .catch(function(){ return blob; })
                    .then(function(stamped){ zip.file(fname, stamped); });
            });
            return Promise.all(jobs).then(function(){
                // STORE (no recompress) - PNGs are already compressed; this is
                // far faster and lighter than DEFLATE on dozens of images.
                return zip.generateAsync({ type: 'blob', compression: 'STORE' })
                    .then(function(zipBlob){ return { zipBlob: zipBlob, manifest: manifest }; });
            });
        });
    }

    // Try to render the whole sheet as a single PNG at EXPORT_DPI. Returns the
    // blob, or null if the sheet is too large to render as one image (big
    // rosters / tall sheets) - in which case production uses the ZIP and the
    // backend marks the order combined_rendered = 0. The size guard also
    // prevents the tab from freezing on an enormous canvas.
    // Render the WHOLE sheet as one combined PNG. The DPI is auto-scaled down
    // so the canvas stays within safe browser limits, so even a tall multi-
    // piece sheet still produces a SINGLE gang-sheet image instead of a pile
    // of individual files. Returns { blob, dpi }, or null only when the sheet
    // is so large that even a low DPI exceeds the pixel-dimension ceiling (the
    // caller then falls back to a per-design ZIP).
    function buildCombinedSheet() {
        return new Promise(function(resolve){
            var wIn = SHEET_W;
            var hIn = sheetState.sheetH;
            var MAX_DIM  = 16384;      // safe single-side pixel ceiling
            var MAX_AREA = 50000000;   // ~50 MP total (keeps canvas memory sane)

            var dpi = EXPORT_DPI;
            dpi = Math.min(dpi, Math.floor(MAX_DIM / Math.max(0.01, wIn)));
            dpi = Math.min(dpi, Math.floor(MAX_DIM / Math.max(0.01, hIn)));
            dpi = Math.min(dpi, Math.floor(Math.sqrt(MAX_AREA / Math.max(0.01, wIn * hIn))));

            // Below this the sheet is enormous (many feet long) - fall back to
            // the per-design ZIP rather than render an unusably coarse image.
            if (dpi < 60) { resolve(null); return; }

            try {
                var c   = document.createElement('canvas');
                var ctx = c.getContext('2d');
                renderSheet(ctx, c, sheetState.placements, sheetState.sheetH, dpi, false, false);
                c.toBlob(function(blob){
                    if (!blob) { resolve(null); return; }
                    injectPngDpi(blob, dpi)
                        .then(function(b){ resolve({ blob: b, dpi: dpi }); })
                        .catch(function(){ resolve({ blob: blob, dpi: dpi }); });
                }, 'image/png');
            } catch (e) {
                resolve(null);
            }
        });
    }

    // Lightweight manifest (no image work) for the admin design list.
    function buildManifest() {
        return designs.map(function(d, i){
            var qty = Math.max(1, parseInt(d.qty, 10) || 1);
            return {
                file: ('0' + (i + 1)).slice(-2) + '_' + btgsbSafeName(d.name) + '_x' + qty + '.png',
                design: d.name, original: d.name,
                w: d.widthIn.toFixed(2), h: d.heightIn.toFixed(2), qty: qty
            };
        });
    }

    /* -- Add to Cart --------------------------------------------- */
    // Primary path: ONE combined gang-sheet PNG (auto-scaled DPI). Fallback,
    // only for sheets too large to render as a single image: a ZIP of the
    // per-design PNGs (the original behavior, kept as a safety net).
    $('#btgsb-order-btn').on('click', function(){
        if (!sheetState.placements.length) return;
        // Final safety net: never let an overlapping layout reach production.
        var plc = sheetState.placements;
        for (var oi = 0; oi < plc.length; oi++) {
            for (var oj = oi + 1; oj < plc.length; oj++) {
                if (rectsOverlap(plc[oi], plc[oj])) {
                    setStatus('Some pieces are overlapping \u2014 click "Auto Layout" or drag them apart, then try again.', 'error');
                    return;
                }
            }
        }
        var $btn = $(this);
        var origText = $btn.text();
        var info = window._btgsb || {};
        $btn.prop('disabled', true).text('Building sheet\u2026');
        setStatus('Rendering your gang sheet\u2026', 'info');

        var manifest = buildManifest();

        function addToCart(sheetUrl, zipUrl, combined) {
            var fd2 = new FormData();
            fd2.append('action', 'btgsb_add_to_cart');
            fd2.append('nonce', BTGSB.nonce);
            fd2.append('sq_inches',     Math.round(info.sqIn || (SHEET_W * sheetState.sheetH)));
            fd2.append('price',         (info.price || 0).toFixed(2));
            fd2.append('height_inches', (info.sheetH || sheetState.sheetH).toFixed(2));
            fd2.append('width_inches',  SHEET_W);
            fd2.append('sheet_url',     sheetUrl || '');
            fd2.append('zip_url',       zipUrl   || '');
            fd2.append('item_count',    info.totalPieces || sheetState.placements.length);
            fd2.append('combined_rendered', combined ? '1' : '0');
            fd2.append('manifest',      JSON.stringify(manifest));
            setStatus('Adding to cart\u2026', 'info');
            return fetch(BTGSB.ajax_url, { method: 'POST', body: fd2, credentials: 'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(cart){
                    if (cart && cart.success) {
                        setStatus('Added to cart! Redirecting\u2026', 'success');
                        try { localStorage.removeItem('btgsb_autosave'); } catch(e){}
                        window.location.href = (cart.data && cart.data.cart_url) ? cart.data.cart_url : BTGSB.cart_url;
                    } else {
                        throw new Error((cart && cart.data) ? cart.data : 'Could not add to cart.');
                    }
                });
        }

        buildCombinedSheet().then(function(combined){
            if (combined && combined.blob) {
                // Combined gang-sheet PNG + per-design ZIP in one request.
                // The ZIP used to be built ONLY on the too-big fallback path,
                // so normal orders never had individual design files on the
                // server. The backend's zip_file branch already accepts both
                // files together and returns both URLs. If the ZIP build
                // fails (e.g. the JSZip CDN is blocked) the order still goes
                // through with just the combined PNG, like before.
                return buildProductionZip().catch(function(){ return null; }).then(function(zipRes){
                    var fd = new FormData();
                    fd.append('action', 'btgsb_save_sheet');
                    fd.append('nonce', BTGSB.nonce);
                    fd.append('sheet_file', combined.blob, 'gang-sheet.png');
                    if (zipRes && zipRes.zipBlob) {
                        manifest = zipRes.manifest;
                        fd.append('zip_file', zipRes.zipBlob, 'production-files.zip');
                    }
                    setStatus('Uploading sheet\u2026', 'info');
                    return fetch(BTGSB.ajax_url, { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(function(r){ return r.json(); })
                        .then(function(up){
                            if (!up || !up.success) throw new Error((up && up.data) ? up.data : 'Upload failed.');
                            var sheetUrl = (up.data && (up.data.sheet_url || up.data.url)) || '';
                            var zipUrl   = (up.data && up.data.zip_url) ? up.data.zip_url : '';
                            return addToCart(sheetUrl, zipUrl, true);
                        });
                });
            }
            // Sheet too large for a single image -> per-design ZIP fallback
            setStatus('Sheet is very large \u2014 packaging individual files\u2026', 'info');
            return buildProductionZip().then(function(zipRes){
                manifest = zipRes.manifest;
                var fd = new FormData();
                fd.append('action', 'btgsb_save_sheet');
                fd.append('nonce', BTGSB.nonce);
                fd.append('zip_file', zipRes.zipBlob, 'production-files.zip');
                setStatus('Uploading files\u2026', 'info');
                return fetch(BTGSB.ajax_url, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(up){
                        if (!up || !up.success) throw new Error((up && up.data) ? up.data : 'Upload failed.');
                        var zipUrl = (up.data && up.data.zip_url) ? up.data.zip_url : '';
                        return addToCart('', zipUrl, false);
                    });
            });
        }).catch(function(err){
            setStatus(err.message || 'Something went wrong adding to cart.', 'error');
            $btn.prop('disabled', false).text(origText);
        });
    });

    /* -- Save This Sheet (local to this browser) ----------------- */
    // NOTE: your backend has no email-resume endpoint, so this saves to the
    // browser's localStorage and reloads automatically on next visit (see the
    // boot section's tryRestoreFromLocal). If you want a real emailed resume
    // link, that needs a new backend AJAX handler - ask and I'll write it.
    $('#btgsb-save-btn').on('click', function(){
        $('#btgsb-save-modal').toggle();
    });
    $('#btgsb-save-submit').on('click', function(){
        if (!designs.length) {
            $('#btgsb-save-status').css('color', '#c0392b').text('Add at least one design first.');
            return;
        }
        try {
            localStorage.setItem('btgsb_autosave', JSON.stringify(buildSavePayload()));
            $('#btgsb-save-status').css('color', '#27ae60')
                .text('Saved to this browser \u2014 it will reload automatically next time you open the builder here.');
        } catch (e) {
            $('#btgsb-save-status').css('color', '#c0392b')
                .text('Could not save (browser storage full or unavailable).');
        }
    });

    /* -- Restore a saved sheet payload --------------------------- */
    function finalizeRestore(payload) {
        if (!payload || !payload.designs) return;
        isRestoring = true;

        nextBatchNameCounter = payload.nextBatchNameCounter || 1;

        // Rebuild designs first; remember new id by original array index
        var indexToId = [];
        designs = [];
        nextId = 0;
        payload.designs.forEach(function(pd, i){
            var did = nextId++;
            indexToId[i] = did;
            designs.push({
                id: did, name: pd.name, dataUrl: pd.dataUrl, imgEl: null,
                nW: pd.nW, nH: pd.nH, widthIn: pd.widthIn, heightIn: pd.heightIn,
                qty: pd.qty, locked: pd.locked, rotated: pd.rotated, rotLock: !!pd.rotLock, hasAlpha: pd.hasAlpha,
                batchId: (pd.batchId === null || pd.batchId === undefined) ? null : pd.batchId
            });
        });

        // Rebuild batches; items reference designs by stored index (di)
        batches = [];
        nextBatchId = 0;
        nextBatchItemId = 0;
        (payload.batches || []).forEach(function(pb){
            var bid = nextBatchId++;
            var items = (pb.items || []).map(function(it){
                return {
                    id: nextBatchItemId++,
                    kind: it.kind, text: it.text,
                    designId: indexToId[it.di],
                    fontOverride: it.fontOverride || null
                };
            }).filter(function(it){ return it.designId !== undefined; });
            // Point member designs at this new batch id
            items.forEach(function(it){
                var d = findDesign(it.designId);
                if (d) d.batchId = bid;
            });
            batches.push({
                id: bid, name: pb.name, font: pb.font, color: pb.color,
                nameHeight: pb.nameHeight, numberHeight: pb.numberHeight,
                open: pb.open, items: items
            });
        });

        var loadJobs = designs.map(function(d){
            return new Promise(function(resolve){
                var img = new Image();
                img.onload  = function(){ d.imgEl = img; resolve(); };
                img.onerror = function(){ resolve(); };
                img.src = d.dataUrl;
            });
        });

        Promise.all(loadJobs).then(function(){
            recalculate();
            // Re-apply anchored positions stored by design index
            var anchors = (payload.layout && payload.layout.anchors) ? payload.layout.anchors : [];
            anchors.forEach(function(a){
                var targetId = indexToId[a.di];
                for (var i=0;i<sheetState.placements.length;i++){
                    var p = sheetState.placements[i];
                    if (p.designId === targetId && !p.anchored) {
                        p.x = a.x; p.y = a.y; p.anchored = true;
                        break;
                    }
                }
            });
            isRestoring = false;
            undoStack = [];
            refresh();
        });
    }

    /* ============================================================
       BACKEND-TOUCHING HANDLER #3 - LOAD RESUME LINK
       If a ?btgsb_resume=TOKEN param is present, fetch the saved
       payload from the backend. Verify the 'action' string.
       ============================================================ */
    function tryRestoreFromUrl() {
        var params = new URLSearchParams(window.location.search);
        var token = params.get('btgsb_resume');
        if (!token) return false;
        setStatus('Loading your saved sheet\u2026', 'info');
        var fd = new FormData();
        fd.append('action', 'btgsb_load_sheet');
        fd.append('nonce', BTGSB.nonce);
        fd.append('token', token);
        fetch(BTGSB.ajax_url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res && res.success && res.data && res.data.payload) {
                    var payload = (typeof res.data.payload === 'string') ? JSON.parse(res.data.payload) : res.data.payload;
                    finalizeRestore(payload);
                    setStatus('', 'info');
                } else {
                    setStatus('Could not load that saved sheet.', 'error');
                }
            })
            .catch(function(){ setStatus('Could not load that saved sheet.', 'error'); });
        return true;
    }

    function tryRestoreFromLocal() {
        try {
            var raw = localStorage.getItem('btgsb_autosave');
            if (!raw) return false;
            var payload = JSON.parse(raw);
            if (!payload || !payload.designs || !payload.designs.length) return false;
            finalizeRestore(payload);
            return true;
        } catch(e){ return false; }
    }

    /* -- Boot ---------------------------------------------------- */
    if (!tryRestoreFromUrl()) {
        if (!tryRestoreFromLocal()) {
            refresh();
        }
    }

});
</script>
<?php
    return ob_get_clean();
}


/* ══ BOOT ═══════════════════════════════════════════════════════════════
   Nothing registers at load. The shortcode waits for plugins_loaded at
   priority 999, after WPCode has evaluated its snippets, and the module
   stands down if the DTF Studio - Frontend snippet is still active — so
   the shortcode is never registered twice.

   Only a tidiness guard, not a crash guard: this file declares
   btdtf_render_builder while the snippet declares btgsb_render_builder,
   so a fatal redeclare is impossible in any activation order.
   ═══════════════════════════════════════════════════════════════════════ */
function btdtf_frontend_snippet_is_active() {
    return function_exists('btgsb_render_builder');
}

add_action('plugins_loaded', function () {
    if (btdtf_frontend_snippet_is_active()) return;   // snippet still on — stay dormant
    btdtf_frontend_register_hooks();
}, 999);

function btdtf_frontend_register_hooks() {
    add_shortcode('gang_sheet_builder', 'btdtf_render_builder');
    add_filter('no_texturize_shortcodes', function($s){ $s[]='gang_sheet_builder'; return $s; });
}
