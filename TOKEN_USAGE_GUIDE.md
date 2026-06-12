# Token Usage Guide

## Vì sao thread này tốn token nhanh

- Token không được tính theo số câu hỏi, mà theo tổng lượng ngữ cảnh model phải đọc lại ở mỗi lượt.
- Mỗi lượt trả lời phải mang theo:
  - lịch sử hội thoại trước đó
  - instruction của session và project
  - nội dung file đã mở
  - output terminal/tool
  - ảnh đính kèm

## Những thứ tốn token nhất trong thread này

- Ảnh chụp màn hình toàn màn hình
- Output dài từ terminal như `rg`, `EXPLAIN`, `SHOW INDEX`, `git status`, log lỗi
- Việc mở nhiều file code trong cùng một thread
- Đổi nhiều chủ đề trong cùng một cuộc hội thoại

## Ví dụ các chủ đề đã bị dồn chung

- Lỗi validation API
- Tối ưu index SQL
- Git commit nhầm
- Cache Postman
- Sửa config `EVENTOS_WEB_API_BASE_URL`
- Lỗi `.env`

Mỗi lần đổi chủ đề như vậy, model vẫn phải giữ lại ngữ cảnh cũ, nên token tăng nhanh.

## Cách làm việc để tiết kiệm token

### 1. Mỗi chủ đề dùng một thread riêng

Nên tách như sau:

- Thread 1: SQL, index, `EXPLAIN`
- Thread 2: Git, branch, commit
- Thread 3: Config `.env`, API URL, middleware

### 2. Ưu tiên gửi text thay vì ảnh

Nên gửi:

```text
1 đoạn log 10-20 dòng
1 câu SQL
1 stack trace ngắn
1 block config liên quan
```

Thay vì gửi ảnh toàn màn hình nếu lỗi chỉ nằm ở 1-2 dòng.

### 3. Chỉ gửi phần liên quan trực tiếp

Ví dụ:

- Nếu lỗi `.env`, chỉ gửi dòng lỗi và đoạn `.env` liên quan
- Nếu lỗi SQL, chỉ gửi query và `EXPLAIN`
- Nếu lỗi API, chỉ gửi route, payload, response

### 4. Yêu cầu trả lời ngắn khi không cần đào sâu

Có thể nhắn kiểu:

```text
Trả lời ngắn, chưa cần sửa code.
```

hoặc

```text
Chỉ phân tích nguyên nhân, không cần scan nhiều file.
```

### 5. Khi xong một vấn đề thì mở thread mới

Đây là cách tiết kiệm token hiệu quả nhất.

## Mẫu nhắn giúp tiết kiệm token

### Mẫu debug ngắn

```text
Đây là log lỗi:
...

Giải thích ngắn nguyên nhân và hướng xử lý. Không cần scan nhiều file.
```

### Mẫu tối ưu SQL

```text
Đây là query và EXPLAIN:
...

Chỉ đề xuất index hoặc rewrite query. Trả lời ngắn.
```

### Mẫu sửa code

```text
Sửa giúp tôi lỗi này.
Chỉ đọc các file liên quan trực tiếp.
Sau khi sửa, tóm tắt ngắn.
```

## Kết luận ngắn

Nếu muốn giảm hao token:

- tách thread theo từng chủ đề
- gửi text thay vì ảnh
- chỉ gửi phần cần thiết
- yêu cầu trả lời ngắn khi phù hợp

