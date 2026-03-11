# Woo Huifu Gateway

woocommerce 对接汇付天下支付插件

## 部署
将目录上传到 wp-content/plugins 目录下即可


#密钥生成
```
openssl 
genrsa -out 10123070726.key 2048
req -new -x509 -key 10123070726.key -out 10123070726.cer
pkcs12 -export -name huifu -in 10123070726.cer -inkey 10123070726.key -out 10123070726.pfx
```
