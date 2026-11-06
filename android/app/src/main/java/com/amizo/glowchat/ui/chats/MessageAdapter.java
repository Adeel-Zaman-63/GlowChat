package com.amizo.glowchat.ui.chats;

import android.view.Gravity;
import android.widget.FrameLayout;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.DiffUtil;
import androidx.recyclerview.widget.ListAdapter;
import androidx.recyclerview.widget.RecyclerView;

import com.amizo.glowchat.R;

import java.text.DateFormat;
import java.util.Date;

public class MessageAdapter
        extends ListAdapter<ChatMessage, MessageAdapter.MessageViewHolder> {

    private static final DiffUtil.ItemCallback<ChatMessage> DIFF_CALLBACK =
            new DiffUtil.ItemCallback<ChatMessage>() {

                @Override
                public boolean areItemsTheSame(
                        @NonNull ChatMessage oldItem,
                        @NonNull ChatMessage newItem
                ) {
                    return oldItem.getMessageId().equals(newItem.getMessageId());
                }

                @Override
                public boolean areContentsTheSame(
                        @NonNull ChatMessage oldItem,
                        @NonNull ChatMessage newItem
                ) {
                    return oldItem.getBody().equals(newItem.getBody())
                            && oldItem.getCreatedAt() == newItem.getCreatedAt()
                            && oldItem.isOutgoing() == newItem.isOutgoing()
                            && oldItem.getStatus().equals(newItem.getStatus());
                }
            };

    public MessageAdapter() {
        super(DIFF_CALLBACK);
    }

    @NonNull
    @Override
    public MessageViewHolder onCreateViewHolder(
            @NonNull ViewGroup parent,
            int viewType
    ) {
        View view = LayoutInflater.from(parent.getContext())
                .inflate(R.layout.item_message, parent, false);

        return new MessageViewHolder(view);
    }

    @Override
    public void onBindViewHolder(
            @NonNull MessageViewHolder holder,
            int position
    ) {
        holder.bind(getItem(position));
    }

    static class MessageViewHolder extends RecyclerView.ViewHolder {

        private final LinearLayout bubble;
        private final TextView body;
        private final TextView time;
        private final TextView status;

        MessageViewHolder(@NonNull View itemView) {
            super(itemView);

            bubble = itemView.findViewById(R.id.message_bubble);
            body = itemView.findViewById(R.id.text_message_body);
            time = itemView.findViewById(R.id.text_message_time);
            status = itemView.findViewById(R.id.text_message_status);
        }

        void bind(ChatMessage message) {

            body.setText(message.getBody());

            time.setText(
                    DateFormat.getTimeInstance(DateFormat.SHORT)
                            .format(new Date(message.getCreatedAt()))
            );

            ViewGroup.LayoutParams layoutParams = bubble.getLayoutParams();

            if (layoutParams instanceof FrameLayout.LayoutParams) {
                FrameLayout.LayoutParams params =
                        (FrameLayout.LayoutParams) layoutParams;

                params.gravity = message.isOutgoing()
                        ? Gravity.END
                        : Gravity.START;

                bubble.setLayoutParams(params);
            }

            bubble.setBackgroundResource(
                    message.isOutgoing()
                            ? R.drawable.bg_message_sent
                            : R.drawable.bg_message_received
            );

            if (message.isOutgoing()) {
                status.setVisibility(View.VISIBLE);
                status.setText(getStatusSymbol(message.getStatus()));
            } else {
                status.setVisibility(View.GONE);
            }
        }

        private String getStatusSymbol(String messageStatus) {

            if (ChatMessage.STATUS_PENDING.equals(messageStatus)) {
                return "◷";
            }

            if (ChatMessage.STATUS_SENT.equals(messageStatus)) {
                return "✓";
            }

            if (ChatMessage.STATUS_DELIVERED.equals(messageStatus)) {
                return "✓✓";
            }

            if (ChatMessage.STATUS_READ.equals(messageStatus)) {
                return "✓✓";
            }

            if (ChatMessage.STATUS_FAILED.equals(messageStatus)) {
                return "!";
            }

            return "";
        }
    }
}
