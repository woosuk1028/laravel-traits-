<?php
namespace App\Traits;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;

trait DataTraits
{
    //정렬 초기화
    public function initializeSorting($request) {
        $this->order_key = $request->order_key;
        $this->order = $request->order == '' ? 'DESC' : $request->order;
        if ($this->order == "DESC") {
            $this->return_order = 'ASC';
            $this->return_cls = '-desc';
        } else {
            $this->return_order = 'DESC';
            $this->return_cls = '-asc';
        }
    }

    //필터
    private function applyFilters($query, $request, $filters)
    {
        foreach ($filters as $filter) {
            $value = $request->input($filter);
            if ($request->has($filter) && $value != "") {
                $query->where($filter, $value);
            }
            else
            {
                // 예외용
                if(!$request->has('run_state') && $filter == 'run_state')
                {
                    $query->where($filter, 1);
                }
            }
        }
        return $query;
    }

    //검색
    private function applySearch($query, $request, $search_keys)
    {
        if ($request->has('search')) {
            $query->where(function($query) use($search_keys, $request){
                foreach ($search_keys as $key) {
                    $query->orWhere($key, "like", "%" . $request->search . "%");
                }
            });
        }

        return $query;
    }

    //정렬
    private function orderData($query, $request, $defulat_order, $order_type="DESC")
    {
        $order_key  = $request->input('order_key');
        $order      = $request->input('order');

        if($request->has('order_key') && $request->has('order'))
        {
            $query->orderBy($order_key, $order);
        }
        else
        {
            $query->orderBy($defulat_order, $order_type);
        }

        return $query;
    }

    //데이터 추출 준비
    private function prepareData($query, $request)
    {
        // 모든 쿼리스트링을 가져오고 빈 값도 유지하도록 설정
        $queryString = collect($request->all())
            ->mapWithKeys(function ($value, $key) {
                return [$key => $value ?? '']; // null 값을 빈 문자열로 변환
            })
            ->toArray();

        // 쿼리스트링을 추가하며 페이지네이션 데이터 생성
        $data = $query->paginate(config('variables.pageCnt'))->appends($queryString);

        return $data;
    }

    private function prepareDataSmall($query, $request)
    {
        // 모든 쿼리스트링을 가져오고 빈 값도 유지하도록 설정
        $queryString = collect($request->all())
            ->mapWithKeys(function ($value, $key) {
                return [$key => $value ?? '']; // null 값을 빈 문자열로 변환
            })
            ->toArray();

        // 쿼리스트링을 추가하며 페이지네이션 데이터 생성
        $data = $query->paginate(5)->appends($queryString);

        return $data;
    }

    //현재 조회된 열 이름 get
    private function get_row($value)
    {
        $data = array();

        if (!empty($value))
        {
            foreach ($value as $key)
            {
                foreach (json_decode($key) as $key2 => $val2)
                {
                    array_push($data, $key2);
                }
                break;
            }
        }

        return $data;
    }

    //테이블 정렬 생성
    private function order_make($value, $request)
    {
        $data = array();

        $url = $request->fullUrl() . "?";
        if (strpos($request->fullUrl(), '?') !== false)
        {
            $url = $request->fullUrl() . "&";
        }

        foreach($this->get_row($value) as $key => $val)
        {
            $cls = "";
            if ($this->order_key == $val)
            {
                $cls = $this->return_cls;
            }

            $front = "    <a href='" . $url . "order_key=$val&order=$this->return_order'>
                            <i class='la la-sort$cls float-right'></i>
                          </a>";

            $data[$val] = $front;
        }
        return $data;
    }

    //유효성 검사 메서드
    private function validateRequest($request, $rules) {
        return Validator::make($request->all(), $rules)->validate();
    }    

private function curl_post($url, $field)
    {
        $ch = curl_init();
        $headers  = [
            //            'x-api-key: XXXXXX',
            'Content-Type: application/json'
        ];

        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($field));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result     = curl_exec ($ch);
        //        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return $result;
    }

    private function file_upload($request, $key, $disk)
    {
        $result = "";
        if ($request->hasFile($key)) {
            if ($request->file($key)->isValid()) {
                $rand = rand(000000, 999999);
                $file = $request->file($key);
                $filename = $rand . "_" . time() . '.' . $file->getClientOriginalExtension();

                // Save file to public disk
                Storage::disk('public')->put("{$disk}/{$filename}", file_get_contents($file));

                // If you want to return the URL
                $url = Storage::url("{$disk}/{$filename}");
                $url = url($url);
                $result = $url;
            }
        }

        return $result;
    }

    private function file_url_upload($url, $disk)
    {
        try {
            if(empty($url))
                return "";

            // 저장할 이미지명 생성
            $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));

            // 확장자가 없거나 예상치 못한 경우 기본 확장자로 설정
            if (!$ext || !in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $ext = 'jpg'; // 기본값
            }

            $rand = rand(000000, 999999);
            $filename = $rand . "_" . time() . '.png';

            // 저장할 파일 경로
            $filePath = "{$disk}/{$filename}";

            // cURL 초기화
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'PostmanRuntime/7.42.0');
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
            curl_setopt($ch, CURLOPT_AUTOREFERER, false);
            curl_setopt($ch, CURLOPT_REFERER, 'https://google.com');
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            // 실행 및 결과 저장
            $contents = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $error_message = curl_error($ch); // cURL 에러 메시지
            $error_no = curl_errno($ch);      // cURL 에러 코드
            curl_close($ch);

            // 요청 실패 처리
            if ($contents === false || $http_code !== 200) {
                // cURL 실패 시 에러 로그 출력
                \Log::error($disk." => cURL Error ({$error_no}): {$error_message}");
                \Log::error($disk." => HTTP Code: {$http_code}");
                return '500';
            }

            // 파일 저장
            Storage::disk('public')->put($filePath, $contents);

            // 저장된 파일 URL 반환
            $storedUrl = Storage::url($filePath);
            return url($storedUrl);
        } catch (\Exception $e) {
            // 에러 로그 기록
            \Log::error("Failed to upload file from URL: " . $e->getMessage());
            return null;
        }
    }
}
