jQuery(document).ready(function ($) {
  // 實例化dropzone
  const myDropzone = $(".dropzone");
  myDropzone.dropzone({
    url: SERVER_DATA.SERVER_URL,
    // 額外送到server的參數
    params: (files, xhr, chunk) => ({ action: "uploadProductExcel" }),
    success: (file, response) => {
      // console.log("success", response);
      // console.log(file, response);
      console.log(response);
    },
    complete: (file) => console.log("不論上傳失敗還是成功的消息"),
    paramName: "myExcel", // server接收file的key
  });

  // const inputFile = document.querySelector("#upload");
  // inputFile?.addEventListener("change", (e) => {
  //   const file = e.target.files[0];
  //   uploadProductExcel({ action: "uploadProductExcel", file: file });
  // });
});

function uploadProductExcel(data) {
  makeRequest("post", data)
    .then((res) => console.log(res))
    .catch((err) => console.log(err));
}

function objToFormdata(obj) {
  let formData = new FormData();
  Object.entries(obj).forEach(([key, value]) => {
    formData.append(key, value);
  });
  return formData;
}

function makeRequest(method, data) {
  console.log({ method, data });
  return new Promise((resolve, reject) => {
    $.ajax({
      url: SERVER_DATA.SERVER_URL,
      method: method ? method : "get",
      data: objToFormdata(data),
      processData: false, //true是將data轉換成查詢字串content-type="applction/x-www-form-urlencoded", false則multipart/form-data
      contentType: false, //ture是預設application/x-www-form-urlencoded, false讓瀏覽器自動決定
      success: (res) => {
        resolve(res);
      },
      error: (err) => {
        reject(err);
      },
    });
  });
}

// 使用前端套件xlsx但在這邊不推薦用
async function createProduct(action, data) {
  let workbook = XLSX.read(data, { type: "array" });
  const sheetName = workbook.SheetNames[0]; //頁籤
  const worksheet = workbook.Sheets[sheetName];
  const products = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
  let organizedProducts = [];
  for (let i = 1; i < products.length; i++) {
    organizedProducts = [
      ...organizedProducts,
      {
        post_title: products[i][0],
        post_content: products[i][1],
        post_status: products[i][2],
        post_type: products[i][3],
      },
    ];
  }

  console.log(products, organizedProducts);

  let formdata = new FormData();
  formdata.append("action", action);
  formdata.append("data", data);

  const res = await fetch(SERVER_DATA.SERVER_URL, {
    method: "post",
    body: formdata,
  });

  console.log(await res.text());
}

// 之後可以建立一個全域配置的var的檔案
// const SERVER_URL = SERVER_DATA.SERVER_URL;
console.log("this is admin excel page with index.js ");
