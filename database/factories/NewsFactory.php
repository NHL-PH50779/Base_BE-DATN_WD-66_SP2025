<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class NewsFactory extends Factory
{
    protected $model = \App\Models\News::class;

    public function definition()
    {
        // Một số tiêu đề mẫu liên quan máy tính
        $titles = [
            'Cách chọn mua laptop phù hợp cho sinh viên',
            'Top 5 CPU mạnh nhất năm 2025',
            'So sánh card đồ họa NVIDIA và AMD',
            'Các xu hướng công nghệ mới trong ngành IT',
            'Làm thế nào để tối ưu hóa hiệu suất máy tính của bạn',
            'Bảo mật máy tính: Những điều bạn cần biết',
            'Hướng dẫn lắp ráp PC gaming tại nhà',
            'Phần mềm diệt virus tốt nhất cho năm 2025',
            'So sánh các loại ổ cứng SSD và HDD',
            'Những lỗi phổ biến khi sử dụng máy tính và cách khắc phục',
        ];

        // Chọn ngẫu nhiên 1 tiêu đề trong danh sách
        $title = $this->faker->randomElement($titles);

        return [
            'title' => $title,
            'content' => $this->faker->paragraphs(5, true), // 5 đoạn văn
            'thumbnail' => null, // Hoặc bạn có thể cho URL ảnh mẫu ở đây
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => now(),
        ];
    }
}
