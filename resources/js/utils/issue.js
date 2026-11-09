/**
 * Build the detail URL for an issue.
 *
 * The backend appends a `url` attribute shaped like `/issues/{id}/{slug}`.
 * Fall back to the plain id-based URL when the slug is unavailable
 * (for example, hand-built objects that were not serialized from the model).
 */
export function issueUrl(issue) {
    if (issue?.url) {
        return issue.url;
    }

    return issue?.id ? `/issues/${issue.id}` : '/issues';
}
