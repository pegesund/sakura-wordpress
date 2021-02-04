;;; -*- encoding:utf-8 -*-  ---
;; replace the list of REPLs types and set some default
((nil
  (eval add-hook 'before-save-hook #'delete-trailing-whitespace nil t)
  (toc-org-max-depth . 3)
  (tab-width . 2)
  (org-link-file-path-type . relative)))
