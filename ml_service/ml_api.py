import gzip
import os
import sys
import xml.etree.ElementTree as ET
from pathlib import Path
from typing import Any, Dict, List, Optional
from fastapi import FastAPI, HTTPException
import numpy as np
import pandas as pd
from pydantic import BaseModel

app = FastAPI(
    title="SPK Machine Learning API - UD. Zufri",
    description="Layanan API Inferensi Machine Learning (K-Means & Linear Regression) untuk SPK Stok UMKM.",
    version="1.0.0"
)

# Direktori model: cek direktori lokal saat ini (ml_service) atau fallback ke direktori induk (finals)
LOCAL_DIR = Path(__file__).resolve().parent
FINALS_DIR = LOCAL_DIR if (LOCAL_DIR / "model_linear_regression.mod").exists() else LOCAL_DIR.parent

# Centroid dan parameter garis regresi dimuat langsung dari berkas model .mod RapidMiner
CENTROIDS: Dict[int, np.ndarray] = {}
CLUSTER_LABELS = {
    0: "Sangat Laris",
    1: "Cukup",
    2: "Kurang",
}

BASELINE_INTERCEPT: Optional[float] = None
BASELINE_SLOPE: Optional[float] = None
MOD_SOURCES: Dict[str, Any] = {
    "linear_regression": None,
    "kmeans": None,
}

def load_rapidminer_models():
    global BASELINE_INTERCEPT, BASELINE_SLOPE, CENTROIDS, MOD_SOURCES

    lr_mod_path = FINALS_DIR / "model_linear_regression.mod"
    if not lr_mod_path.exists():
        raise FileNotFoundError(
            f"Berkas model RapidMiner tidak ditemukan: {lr_mod_path}. "
            "Pastikan berkas model fisik tersedia pada direktori finals."
        )

    try:
        with gzip.open(lr_mod_path, "rb") as f:
            root = ET.fromstring(f.read())
        coeffs = [float(x.text) for x in root.findall(".//LinearRegressionModel/default/coefficients/double")]
        if len(coeffs) < 2:
            raise ValueError(f"Koefisien regresi tidak valid pada {lr_mod_path.name}")
        # Pada model regresi RapidMiner: elemen pertama koefisien fitur (slope), elemen kedua intercept
        BASELINE_SLOPE = round(coeffs[0], 4)
        BASELINE_INTERCEPT = round(coeffs[1], 4)
        MOD_SOURCES["linear_regression"] = {
            "path": str(lr_mod_path),
            "file_name": lr_mod_path.name,
            "intercept": BASELINE_INTERCEPT,
            "slope": BASELINE_SLOPE,
            "loaded": True,
        }
        print(f"[OK] Model RapidMiner dimuat: {lr_mod_path.name} (Intercept: {BASELINE_INTERCEPT}, Slope: {BASELINE_SLOPE})")
    except Exception as e:
        raise RuntimeError(f"Gagal memproses model linear regression: {e}")

    km_mod_path = FINALS_DIR / "model_kmeans.mod"
    if not km_mod_path.exists():
        raise FileNotFoundError(
            f"Berkas model RapidMiner tidak ditemukan: {km_mod_path}. "
            "Pastikan berkas model fisik tersedia pada direktori finals."
        )

    try:
        with gzip.open(km_mod_path, "rb") as f:
            root = ET.fromstring(f.read())
        extracted_centroids = {}
        idx = 0
        for c_elem in root.findall(".//com.rapidminer.operator.clustering.Centroid"):
            coords = [float(x.text) for x in c_elem.findall("./centroid/double")]
            if coords:
                extracted_centroids[idx] = np.array([round(c, 4) for c in coords], dtype=float)
                idx += 1
        if len(extracted_centroids) != 3:
            raise ValueError(f"Jumlah centroid pada {km_mod_path.name} ({len(extracted_centroids)}) bukan 3")
        CENTROIDS = extracted_centroids
        MOD_SOURCES["kmeans"] = {
            "path": str(km_mod_path),
            "file_name": km_mod_path.name,
            "centroids": {k: v.tolist() for k, v in CENTROIDS.items()},
            "loaded": True,
        }
        print(f"[OK] Model RapidMiner dimuat: {km_mod_path.name} (3 Centroid berhasil diekstrak)")
    except Exception as e:
        raise RuntimeError(f"Gagal memproses model kmeans: {e}")

load_rapidminer_models()

class RegressionRequest(BaseModel):
    series: Optional[List[float]] = None
    target_period: Optional[int] = None

class KMeansRequest(BaseModel):
    total_terjual: float
    frekuensi: float
    total_omset: float
    z_scaled: Optional[bool] = False

class ClusterRequest(BaseModel):
    features: Dict[str, List[float]]

@app.get("/")
def root():
    return {
        "status": "online",
        "service": "SPK UD. Zufri Machine Learning Inference Engine",
        "models": ["K-Means Clustering (k=3)", "Linear Regression (y = a + bx)"],
        "rapidminer_mod_loaded": {
            "linear_regression": MOD_SOURCES["linear_regression"] is not None,
            "kmeans": MOD_SOURCES["kmeans"] is not None,
        },
        "docs_url": "/docs"
    }

@app.get("/model/info")
def model_info():
    if not CENTROIDS or BASELINE_INTERCEPT is None or BASELINE_SLOPE is None:
        raise HTTPException(status_code=500, detail="Model fisik RapidMiner (.mod) belum dimuat.")

    return {
        "source": "RapidMiner Studio 7.1 (.mod files)",
        "mod_files_loaded": MOD_SOURCES,
        "kmeans": {
            "k": 3,
            "metric": "Squared Euclidean Distance",
            "silhouette_coefficient": 0.8998,
            "davies_bouldin_index": -0.396,
            "clusters": {
                0: {"label": "Sangat Laris", "centroid": CENTROIDS[0].tolist()},
                1: {"label": "Cukup", "centroid": CENTROIDS[1].tolist()},
                2: {"label": "Kurang", "centroid": CENTROIDS[2].tolist()}
            }
        },
        "linear_regression": {
            "formula": f"Y = {BASELINE_INTERCEPT} + ({BASELINE_SLOPE} * X)",
            "intercept": BASELINE_INTERCEPT,
            "slope": BASELINE_SLOPE,
            "evaluation_21_months": {
                "rmse": 701.653,
                "mae": 529.610,
                "mape": 11.14,
                "accuracy_pct": 88.86,
                "squared_correlation": 0.612
            }
        }
    }

def fit_linear_series(y_slice):
    k = len(y_slice)
    x = np.arange(1, k + 1)
    x_m = np.mean(x)
    y_m = np.mean(y_slice)
    denom = np.sum((x - x_m) ** 2)
    b = float(np.sum((x - x_m) * (y_slice - y_m)) / denom) if denom != 0 else 0.0
    a = float(y_m - b * x_m)
    raw = float(a + b * (k + 1))
    return a, b, raw

@app.post("/predict/linear-regression")
def predict_regression(req: RegressionRequest):
    if req.series and len(req.series) >= 2:
        y = np.array(req.series, dtype=float)
        n = len(y)
        a, b, raw_pred = fit_linear_series(y)
        target_x = req.target_period if req.target_period is not None else (n + 1)
        if req.target_period is not None:
            raw_pred = float(a + b * req.target_period)
        units = int(np.ceil(max(0.0, raw_pred)))
        return {
            "status": "success",
            "source": "python_ml_api",
            "mode": "custom_product_series",
            "intercept": round(float(a), 4),
            "slope": round(float(b), 4),
            "target_period": target_x,
            "raw_prediction": round(raw_pred, 4),
            "units_recommended": units
        }

    if BASELINE_INTERCEPT is None or BASELINE_SLOPE is None:
        raise HTTPException(status_code=500, detail="Model Linear Regression .mod belum dimuat.")

    period = req.target_period or 22
    raw_pred = BASELINE_INTERCEPT + BASELINE_SLOPE * period
    units = int(np.ceil(max(0.0, raw_pred)))
    return {
        "status": "success",
        "source": "python_ml_api",
        "mode": "storewide_baseline",
        "mod_file": MOD_SOURCES["linear_regression"]["file_name"],
        "intercept": BASELINE_INTERCEPT,
        "slope": BASELINE_SLOPE,
        "target_period": period,
        "raw_prediction": round(raw_pred, 4),
        "units_recommended": units,
        "metrics": {
            "rmse": 701.653,
            "mae": 529.610,
            "mape": 11.14,
            "accuracy_pct": 88.86,
            "squared_correlation": 0.612
        }
    }

@app.post("/forecast/linear-regression")
def forecast_regression(req: RegressionRequest):
    if not req.series or len(req.series) < 3:
        raise HTTPException(status_code=400, detail="Data deret waktu minimal 3 periode bulan untuk analisis forecast.")

    values = np.array(req.series, dtype=float)
    n = len(values)
    a, b, raw = fit_linear_series(values)

    errors = []
    actual = []
    val_preds = []
    for i in range(2, n):
        _, _, pred_raw = fit_linear_series(values[:i])
        pred = max(0.0, pred_raw)
        act = values[i]
        errors.append(pred - act)
        actual.append(act)
        val_preds.append(pred)

    errors = np.array(errors)
    actual = np.array(actual)
    mse = float(np.mean(errors ** 2))
    mae = float(np.mean(np.abs(errors)))
    rmse = float(np.sqrt(mse))

    mean_act = np.mean(actual)
    sst = float(np.sum((actual - mean_act) ** 2))
    r_squared = float(1.0 - np.sum(errors ** 2) / sst) if (len(actual) > 1 and sst > 0) else None

    return {
        "status": "success",
        "source": "python_ml_api",
        "a": float(a),
        "b": float(b),
        "raw": float(raw),
        "units": int(np.ceil(max(0.0, raw))),
        "mse": float(mse),
        "rmse": float(rmse),
        "mae": float(mae),
        "r_squared": float(r_squared) if r_squared is not None else None,
        "validation_points": len(errors),
        "validation_predictions": [float(p) for p in val_preds],
        "validation_actual": [float(a) for a in actual]
    }

@app.post("/predict/kmeans")
def predict_kmeans(req: KMeansRequest):
    if not CENTROIDS:
        raise HTTPException(status_code=500, detail="Model K-Means .mod belum dimuat.")

    feat = np.array([req.total_terjual, req.frekuensi, req.total_omset], dtype=float)
    distances = {c: float(np.sqrt(np.sum((feat - centroid) ** 2))) for c, centroid in CENTROIDS.items()}
    best_cluster = min(distances, key=distances.get)

    return {
        "status": "success",
        "source": "python_ml_api",
        "mod_file": MOD_SOURCES["kmeans"]["file_name"],
        "cluster": best_cluster,
        "label": CLUSTER_LABELS[best_cluster],
        "distances": {k: round(v, 4) for k, v in distances.items()}
    }

@app.post("/cluster/kmeans")
def cluster_kmeans(req: ClusterRequest):
    if not CENTROIDS:
        raise HTTPException(status_code=500, detail="Model K-Means .mod belum dimuat.")

    if len(req.features) < 3:
        raise HTTPException(status_code=400, detail="K=3 memerlukan minimal 3 produk dengan fitur berbeda.")

    ids = list(req.features.keys())
    data = np.array([req.features[k] for k in ids], dtype=float)
    n, dim = data.shape

    # Rata-rata dan standar deviasi populasi sesuai normalisasi Z-score RapidMiner
    mean = np.mean(data, axis=0)
    sd = np.std(data, axis=0, ddof=0)
    sd[sd == 0] = 1.0

    points = (data - mean) / sd

    # Jarak ke Centroid yang diekstrak langsung dari model_kmeans.mod
    assignments = {}
    distances = {}
    assigned_labels = []
    for i, pid in enumerate(ids):
        pt = points[i]
        dists = {c: float(np.sqrt(np.sum((pt - centroid) ** 2))) for c, centroid in CENTROIDS.items()}
        best_c = min(dists, key=dists.get)
        assignments[pid] = int(best_c)
        distances[pid] = float(dists[best_c])
        assigned_labels.append(best_c)

    # Hitung koefisien Silhouette jika terdapat lebih dari satu klaster
    unique_clusters = set(assigned_labels)
    silhouette = None
    if len(unique_clusters) > 1 and len(ids) > len(unique_clusters):
        try:
            from sklearn.metrics import silhouette_score
            silhouette = round(float(silhouette_score(points, assigned_labels)), 4)
        except Exception:
            silhouette = None

    # Kembalikan koordinat centroid ke skala asli untuk kompatibilitas penyimpanan basis data
    raw_centroids = {}
    for c_idx, c_pt in CENTROIDS.items():
        raw_centroids[c_idx] = [float(round(c_pt[j] * sd[j] + mean[j], 4)) for j in range(dim)]

    return {
        "status": "success",
        "source": "python_ml_api",
        "mod_file": MOD_SOURCES["kmeans"]["file_name"],
        "centroids": raw_centroids,
        "labels": CLUSTER_LABELS,
        "assignments": assignments,
        "distances": distances,
        "silhouette": silhouette,
        "mean": [float(round(m, 4)) for m in mean],
        "sd": [float(round(s, 4)) for s in sd],
        "iterations": 1
    }

if __name__ == "__main__":
    import uvicorn
    print("Menjalankan API Server Machine Learning SPK UD. Zufri...")
    print("Dokumentasi interaktif (Swagger UI): http://127.0.0.1:8000/docs")
    uvicorn.run(app, host="127.0.0.1", port=8000)

