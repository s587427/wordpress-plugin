console.log("this is home page")

jQuery(document).ready(function($) {
    $.ajax({
        url: PHPVARS.ajaxurl,
        data: {action: "get_time", age:18, name: "孫悟空"},
        success: (result)=>{
            const jsp = JSON.parse(result)
            console.log(result);
            console.log(jsp);
        },
        error: (err) => {
            console.log(err);
        }
    })
    console.log(PHPVARS);
});